#!/usr/bin/env bash
# Постоянный HTTPS для MAX (webhook + mini-app) через VPS + SSH reverse tunnel.
# Без interstitial-страницы fxTunnel. Локальный service-c остаётся на машине разработчика.
#
# Схема:
#   MAX → https://max-dev.example.com → nginx (VPS) → 127.0.0.1:REMOTE_BIND_PORT → SSH -R → localhost:8083

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TUNNEL_ENV_FILE="${ROOT_DIR}/scripts/vps-tunnel.env"

if [[ -f "${TUNNEL_ENV_FILE}" ]]; then
    # shellcheck disable=SC1090
    set -a
    source "${TUNNEL_ENV_FILE}"
    set +a
fi

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
Использование: $(basename "$0") [команда]

Команды:
  check               — локальный service-c и SSH до VPS
  run                 — SSH reverse tunnel (держите терминал открытым)
  verify              — curl локального и публичного /max-app
  nginx-config        — конфиг nginx + certbot для VPS
  apply-nginx-remote  — записать nginx site на VPS по SSH (sudo)
  print-env           — строки для service-c/.env
  ssh-server-hint     — фрагмент sshd_config для reverse tunnel
  print-ssh-key-setup — как настроить SSH-ключ (без пароля при watch)
  install-autossh     — установить autossh в WSL

Переменные (обязательные для run/check/verify):
  VPS_HOST, VPS_USER, VPS_DOMAIN

Опционально (или scripts/vps-tunnel.env):
  VPS_PORT, VPS_SSH_KEY, REMOTE_BIND_PORT, SERVICE_C_PORT, SSH_OPTS

Пример:
  cp scripts/vps-tunnel.env.example scripts/vps-tunnel.env
  # заполните VPS_HOST, VPS_USER, VPS_DOMAIN

  docker compose up -d service-c
  ./scripts/setup-max-vps.sh
  ./scripts/vps-tunnel.sh apply-nginx-remote
  ./scripts/vps-tunnel-watch.sh
EOF
}

ssh_base_args() {
    local -a args=(-p "${VPS_PORT}")
    local key_path
    key_path="$(resolve_ssh_key_path || true)"
    if [[ -n "${key_path}" ]]; then
        args+=(-i "${key_path}")
    fi
    # shellcheck disable=SC2206
    args+=(${SSH_OPTS})
    printf '%s\n' "${args[@]}"
}

resolve_ssh_key_path() {
    local key="${VPS_SSH_KEY:-}"
    if [[ -z "${key}" ]]; then
        return 0
    fi
    key="${key/#\~/$HOME}"
    if [[ -f "${key}" ]]; then
        echo "${key}"
        return 0
    fi
    echo "WARNING: VPS_SSH_KEY не найден: ${key} — SSH запросит пароль (для watch лучше настроить ключ)." >&2
    echo "  ./scripts/vps-tunnel.sh print-ssh-key-setup" >&2
    return 1
}

scp_base_args() {
    local -a args=(-P "${VPS_PORT}")
    local key_path
    key_path="$(resolve_ssh_key_path || true)"
    if [[ -n "${key_path}" ]]; then
        args+=(-i "${key_path}")
    fi
    printf '%s\n' "${args[@]}"
}

print_ssh_key_setup() {
    require_vps_vars
    cat <<EOF
# Вход на VPS без пароля (один раз, локально в WSL):

ssh-keygen -t ed25519 -f ~/.ssh/id_ed25519 -N ""

ssh-copy-id -i ~/.ssh/id_ed25519.pub -p ${VPS_PORT} ${VPS_USER}@${VPS_HOST}

# Проверка:
ssh -p ${VPS_PORT} -i ~/.ssh/id_ed25519 ${VPS_USER}@${VPS_HOST} 'echo ok'

# В scripts/vps-tunnel.env:
VPS_SSH_KEY=~/.ssh/id_ed25519
EOF
}

require_vps_vars() {
    local missing=0
    for name in VPS_HOST VPS_USER VPS_DOMAIN; do
        if [[ -z "${!name}" ]]; then
            echo "Нужна переменная ${name} (export или scripts/vps-tunnel.env)" >&2
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

public_mini_app_url() {
    echo "$(public_base_url)/max-app"
}

grep_html_signals() {
    local file="$1"
    grep -oE 'Tunnel Warning|id="max-app"|max-build/assets|5174|не собран|Фронтенд не собран' "${file}" 2>/dev/null | head -10 || true
}

print_env_block() {
    require_vps_vars
    local base mini
    base="$(public_base_url)"
    mini="$(public_mini_app_url)"
    cat <<EOF

# --- service-c/.env (MAX через VPS hybrid) ---
APP_URL=${base}
MAX_WEBHOOK_URL=${base}/api/webhooks/max
# MAX_MINI_APP_URL можно не задавать — выводится из MAX_WEBHOOK_URL:
# MAX_MINI_APP_URL=${mini}

# После правки .env:
docker compose exec -T service-c php artisan config:clear
docker compose exec -T service-c php artisan max:webhook:subscribe

# Кабинет MAX → URL мини-приложения: ${mini}
# Рекомендуется отдельный тестовый бот MAX для dev-домена (не prod).
EOF
}

print_nginx_config() {
    require_vps_vars
    cat <<EOF
# /etc/nginx/sites-available/${VPS_DOMAIN}
# Прокси на SSH reverse tunnel (127.0.0.1:${REMOTE_BIND_PORT} на VPS).
# Сначала HTTP:80, затем: sudo certbot --nginx -d ${VPS_DOMAIN}

server {
    listen 80;
    server_name ${VPS_DOMAIN};

    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }

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
#   sudo mkdir -p /var/www/certbot
#   bash scripts/vps-tunnel.sh apply-nginx-remote
#   ssh -t ${VPS_USER}@${VPS_HOST}
#   sudo certbot --nginx -d ${VPS_DOMAIN}
EOF
}

print_ssh_server_hint() {
    cat <<'EOF'
# На VPS в /etc/ssh/sshd_config (для SSH reverse tunnel -R 127.0.0.1:PORT:...):

AllowTcpForwarding yes
GatewayPorts clientspecified

# После правки:
sudo systemctl reload sshd
# или: sudo systemctl reload ssh
EOF
}

verify_tunnel() {
    require_vps_vars
    local mini_url tmp code
    mini_url="$(public_mini_app_url)"
    tmp="$(mktemp)"

    echo "=== Локальный ${LOCAL_URL}/max-app ==="
    if curl -sS --connect-timeout 5 "${LOCAL_URL}/max-app" -o "${tmp}" 2>/dev/null; then
        grep_html_signals "${tmp}"
        if grep -q 'id="max-app"' "${tmp}"; then
            echo "[OK] локальный HTML содержит id=\"max-app\""
        else
            echo "[!!] локальный /max-app без id=\"max-app\" — npm run build?"
        fi
    else
        echo "[!!] ${LOCAL_URL}/max-app недоступен"
    fi

    echo ""
    echo "=== Публичный ${mini_url} ==="
    code="$(curl -s -o "${tmp}" -w '%{http_code}' --connect-timeout 12 --max-time 20 \
        -H 'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)' \
        "${mini_url}" 2>/dev/null || echo 000)"
    echo "HTTP ${code}"
    if [[ "${code}" =~ ^(200|301|302)$ ]]; then
        grep_html_signals "${tmp}"
        if grep -q 'id="max-app"' "${tmp}"; then
            echo "[OK] публичный mini-app отвечает"
        elif grep -qE '502|Bad Gateway|nginx' "${tmp}"; then
            echo "[!!] nginx без туннеля — запустите: ./scripts/vps-tunnel-watch.sh"
        else
            echo "[!!] неожиданный HTML — проверьте APP_URL и npm run build"
        fi
    else
        echo "[!!] публичный URL недоступен (туннель выключен или nginx/cert не настроен)"
        echo "     ./scripts/vps-tunnel.sh check"
    fi

    rm -f "${tmp}"
}

apply_nginx_remote() {
    require_vps_vars
    check_ssh

    local site_path="/etc/nginx/sites-available/${VPS_DOMAIN}"
    local enabled_path="/etc/nginx/sites-enabled/${VPS_DOMAIN}"
    local remote_tmp="/tmp/nginx-site-${VPS_DOMAIN}.conf"
    local local_tmp
    local_tmp="$(mktemp)"

    print_nginx_config > "${local_tmp}"

    local -a scp_args ssh_args
    mapfile -t scp_args < <(scp_base_args)
    mapfile -t ssh_args < <(ssh_base_args)

    echo "Записываю nginx site на ${VPS_USER}@${VPS_HOST}:${site_path}"
    echo "Понадобятся пароль SSH и sudo на VPS (интерактивно)."
    echo ""

    if ! scp "${scp_args[@]}" "${local_tmp}" "${VPS_USER}@${VPS_HOST}:${remote_tmp}"; then
        rm -f "${local_tmp}"
        echo "Не удалось загрузить конфиг (scp)." >&2
        exit 1
    fi
    rm -f "${local_tmp}"

    # Команда в аргументе ssh (не heredoc): stdin остаётся терминалом, иначе -t не
    # выделяет PTY и sudo падает с «no tty present».
    ssh -t "${ssh_args[@]}" "${VPS_USER}@${VPS_HOST}" "
set -euo pipefail
sudo cp $(printf '%q' "${remote_tmp}") $(printf '%q' "${site_path}")
sudo rm -f $(printf '%q' "${remote_tmp}")
sudo mkdir -p /var/www/certbot
sudo ln -sf $(printf '%q' "${site_path}") $(printf '%q' "${enabled_path}")
if [[ -f /etc/nginx/sites-enabled/default ]]; then
    sudo rm -f /etc/nginx/sites-enabled/default
fi
sudo nginx -t
sudo systemctl reload nginx
echo 'Nginx reload OK: ${site_path}'
"

    echo ""
    echo "Если сертификата ещё нет, на VPS (ssh -t ${VPS_USER}@${VPS_HOST}):"
    echo "  sudo certbot --nginx -d ${VPS_DOMAIN}"
    echo ""
    echo "Затем: bash scripts/vps-tunnel-watch.sh"
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
    echo "Готово. Используйте: ./scripts/vps-tunnel-watch.sh"
}

check_all() {
    require_vps_vars
    check_local_service
    check_ssh
    echo
    echo "Домен: $(public_base_url)"
    echo "Mini-app: $(public_mini_app_url)"
    echo "Убедитесь, что A-запись ${VPS_DOMAIN} указывает на ${VPS_HOST}"
    echo "и nginx на VPS проксирует на 127.0.0.1:${REMOTE_BIND_PORT}"

    local code
    code="$(curl -s -o /dev/null -w '%{http_code}' --connect-timeout 5 "$(public_mini_app_url)" 2>/dev/null || echo 000)"
    if [[ "${code}" =~ ^(200|301|302)$ ]]; then
        echo "[OK] Публичный /max-app отвечает (HTTP ${code}) — туннель, вероятно, активен"
    else
        echo "[--] Публичный /max-app: HTTP ${code} (нормально, если туннель ещё не запущен)"
    fi
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
        verify)
            verify_tunnel
            ;;
        nginx-config)
            print_nginx_config
            ;;
        apply-nginx-remote)
            apply_nginx_remote
            ;;
        print-env)
            print_env_block
            ;;
        ssh-server-hint)
            print_ssh_server_hint
            ;;
        print-ssh-key-setup)
            print_ssh_key_setup
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
