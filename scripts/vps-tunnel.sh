#!/usr/bin/env bash
# Постоянный HTTPS для MAX (webhook + mini-app) через VPS + SSH reverse tunnel.
# Без interstitial-страницы fxTunnel. Локальный service-c остаётся на машине разработчика.
#
# Схема:
#   MAX → https://max-dev.example.com → nginx (VPS) → 127.0.0.1:REMOTE_BIND_PORT → SSH -R → localhost:8083

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TUNNEL_SERVICE="${TUNNEL_SERVICE:-service-c}"
PORT="${SERVICE_C_PORT:-8083}"
DOCKER_SERVICE="service-c"
LOCAL_URL="http://127.0.0.1:${PORT}"

VPS_HOST="${VPS_HOST:-}"
VPS_USER="${VPS_USER:-}"
VPS_PORT="${VPS_PORT:-22}"
VPS_DOMAIN="${VPS_DOMAIN:-}"
VPS_SSH_KEY="${VPS_SSH_KEY:-}"
REMOTE_BIND_PORT="${REMOTE_BIND_PORT:-18083}"
SSH_OPTS="${SSH_OPTS:--o ServerAliveInterval=30 -o ServerAliveCountMax=3 -o ExitOnForwardFailure=yes}"

usage() {
    cat <<EOF
Использование: $(basename "$0") [check|run|nginx-config|print-env|install-autossh]

  check         — проверить локальный service-c и SSH до VPS
  run           — запустить SSH reverse tunnel (держите терминал открытым)
  nginx-config  — вывести конфиг nginx + команды certbot для VPS
  print-env     — строки для service-c/.env и команда max:webhook:subscribe
  install-autossh — установить autossh в WSL (для автопереподключения)

Переменные (обязательные для run/check):
  VPS_HOST          IP или хост VPS
  VPS_USER          SSH-пользователь
  VPS_DOMAIN        публичный домен, напр. max-dev.example.com

Опционально:
  VPS_PORT          SSH-порт (по умолчанию: 22)
  VPS_SSH_KEY       путь к приватному ключу
  REMOTE_BIND_PORT  порт на VPS для проброса (по умолчанию: 18083)
  SERVICE_C_PORT    локальный порт service-c (по умолчанию: 8083)
  SSH_OPTS          доп. опции ssh

Пример (полный цикл):

  export VPS_HOST=203.0.113.10
  export VPS_USER=deploy
  export VPS_DOMAIN=max-dev.example.com
  export VPS_SSH_KEY=~/.ssh/id_ed25519

  docker compose up -d service-c
  ./scripts/vps-tunnel.sh check
  ./scripts/vps-tunnel.sh nginx-config | ssh deploy@203.0.113.10 'sudo tee /etc/nginx/sites-available/max-dev'
  # на VPS: sudo ln -s ... && sudo certbot --nginx -d max-dev.example.com && sudo nginx -t && sudo systemctl reload nginx

  ./scripts/vps-tunnel.sh run
  # в другом терминале:
  ./scripts/vps-tunnel.sh print-env
EOF
}

ssh_base_args() {
    local -a args=(-p "${VPS_PORT}")
    if [[ -n "${VPS_SSH_KEY}" ]]; then
        args+=(-i "${VPS_SSH_KEY}")
    fi
    # shellcheck disable=SC2206
    args+=(${SSH_OPTS})
    printf '%s\n' "${args[@]}"
}

require_vps_vars() {
    local missing=0
    for name in VPS_HOST VPS_USER VPS_DOMAIN; do
        if [[ -z "${!name}" ]]; then
            echo "Нужна переменная ${name}" >&2
            missing=1
        fi
    done
    if [[ "${missing}" -eq 1 ]]; then
        usage >&2
        exit 1
    fi
}

check_local_service() {
    local code
    code="$(curl -s -o /dev/null -w '%{http_code}' --connect-timeout 3 "${LOCAL_URL}" || true)"
    if [[ "${code}" != "200" && "${code}" != "301" && "${code}" != "302" && "${code}" != "404" ]]; then
        echo "На ${LOCAL_URL} нет ответа (HTTP ${code:-нет связи})." >&2
        echo "  cd ${ROOT_DIR} && docker compose up -d ${DOCKER_SERVICE}" >&2
        return 1
    fi
    echo "Локальный ${DOCKER_SERVICE}: ${LOCAL_URL} (HTTP ${code})"
}

check_ssh() {
    local -a args
    mapfile -t args < <(ssh_base_args)
    if ssh "${args[@]}" "${VPS_USER}@${VPS_HOST}" 'echo ok' >/dev/null 2>&1; then
        echo "SSH: ${VPS_USER}@${VPS_HOST}:${VPS_PORT} — доступен"
        return 0
    fi
    echo "SSH: не удалось подключиться к ${VPS_USER}@${VPS_HOST}:${VPS_PORT}" >&2
    return 1
}

public_base_url() {
    echo "https://${VPS_DOMAIN}"
}

print_env_block() {
    require_vps_vars
    local base
    base="$(public_base_url)"
    cat <<EOF

# --- service-c/.env (MAX через VPS) ---
APP_URL=${base}
MAX_WEBHOOK_URL=${base}/api/webhooks/max
MAX_MINI_APP_URL=${base}/max-app

# После правки .env:
docker compose exec -T service-c php artisan config:clear
docker compose exec -T service-c php artisan max:webhook:subscribe

# В кабинете MAX укажите тот же MAX_MINI_APP_URL.
EOF
}

print_nginx_config() {
    require_vps_vars
    cat <<EOF
# /etc/nginx/sites-available/${VPS_DOMAIN}
# Прокси на SSH reverse tunnel (слушает только 127.0.0.1:${REMOTE_BIND_PORT} на VPS).

server {
    listen 80;
    server_name ${VPS_DOMAIN};
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl http2;
    server_name ${VPS_DOMAIN};

    # certbot заполнит пути после: sudo certbot --nginx -d ${VPS_DOMAIN}
    ssl_certificate     /etc/letsencrypt/live/${VPS_DOMAIN}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${VPS_DOMAIN}/privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:${REMOTE_BIND_PORT};
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 86400;
    }
}

# На VPS (один раз):
#   sudo apt install -y nginx certbot python3-certbot-nginx
#   sudo tee /etc/nginx/sites-available/${VPS_DOMAIN} < config-from-this-script
#   sudo ln -sf /etc/nginx/sites-available/${VPS_DOMAIN} /etc/nginx/sites-enabled/
#   sudo nginx -t && sudo systemctl reload nginx
#   sudo certbot --nginx -d ${VPS_DOMAIN}
#
# DNS: A-запись ${VPS_DOMAIN} → ${VPS_HOST}
EOF
}

run_tunnel() {
    require_vps_vars
    check_local_service

    local -a args
    mapfile -t args < <(ssh_base_args)

    echo
    echo "SSH reverse tunnel:"
    echo "  VPS 127.0.0.1:${REMOTE_BIND_PORT} → local ${LOCAL_URL}"
    echo "  Публичный URL: $(public_base_url)"
    echo
    print_env_block
    echo
    echo "Остановка: Ctrl+C"
    echo

    exec ssh "${args[@]}" \
        -N \
        -R "127.0.0.1:${REMOTE_BIND_PORT}:127.0.0.1:${PORT}" \
        "${VPS_USER}@${VPS_HOST}"
}

install_autossh() {
    if command -v autossh >/dev/null 2>&1; then
        echo "autossh уже установлен: $(command -v autossh)"
        return 0
    fi
    echo "Устанавливаю autossh ..."
    sudo apt-get update
    sudo apt-get install -y autossh
    echo "Готово. Запуск с автопереподключением:"
    echo "  AUTOSSH_GATETIME=0 autossh -M 0 -f -N \\"
    echo "    -R 127.0.0.1:${REMOTE_BIND_PORT}:127.0.0.1:${PORT} ${VPS_USER}@${VPS_HOST}"
}

check_all() {
    require_vps_vars
    check_local_service
    check_ssh
    echo
    echo "Домен: $(public_base_url)"
    echo "Убедитесь, что A-запись ${VPS_DOMAIN} указывает на ${VPS_HOST}"
    echo "и nginx на VPS проксирует на 127.0.0.1:${REMOTE_BIND_PORT}"
}

main() {
    local action="${1:-check}"

    case "${action}" in
        check)
            check_all
            ;;
        run)
            run_tunnel
            ;;
        nginx-config)
            print_nginx_config
            ;;
        print-env)
            print_env_block
            ;;
        install-autossh)
            install_autossh
            ;;
        -h|--help|help)
            usage
            ;;
        *)
            echo "Неизвестная команда: ${action}" >&2
            usage >&2
            exit 1
            ;;
    esac
}

main "$@"
