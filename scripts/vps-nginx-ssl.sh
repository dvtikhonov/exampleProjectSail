#!/usr/bin/env bash
# HTTPS на VPS: системный nginx (80/443) + Let's Encrypt → Docker gateway (127.0.0.1:8080).
#
# Запускать НА VPS из корня репозитория после:
#   export COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml
#   docker compose up -d
#
# Пример (sslip.io):
#   export VPS_DOMAIN=94-228-117-27.sslip.io
#   export CERTBOT_EMAIL=admin@example.com
#   ./scripts/vps-nginx-ssl.sh all

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VPS_DOMAIN="${VPS_DOMAIN:-}"
CERTBOT_EMAIL="${CERTBOT_EMAIL:-}"
GATEWAY_HTTP_PORT="${GATEWAY_HTTP_PORT:-8080}"
NGINX_SITE_NAME="${NGINX_SITE_NAME:-exampleprojectsail}"
NGINX_AVAILABLE="/etc/nginx/sites-available/${NGINX_SITE_NAME}"
NGINX_ENABLED="/etc/nginx/sites-enabled/${NGINX_SITE_NAME}"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.yml:docker-compose.prod.yml}"

usage() {
    cat <<EOF
Использование: $(basename "$0") <команда>

Команды:
  check         — порты 80/443, nginx, gateway upstream
  install       — apt: nginx, certbot, python3-certbot-nginx
  nginx-config  — вывести конфиг для /etc/nginx/sites-available/
  apply-nginx   — записать конфиг и reload nginx (нужен sudo)
  issue-cert    — certbot certonly --standalone (освобождает 80/443)
  all           — install → issue-cert → apply-nginx → check

Переменные:
  VPS_DOMAIN          публичный домен (обязательно для cert/nginx)
  CERTBOT_EMAIL       email для Let's Encrypt (обязательно для issue-cert/all)
  GATEWAY_HTTP_PORT   upstream Docker gateway (по умолчанию: 8080)
  NGINX_SITE_NAME     имя файла в sites-available (по умолчанию: exampleprojectsail)
  COMPOSE_FILE        overlay compose (по умолчанию: docker-compose.yml:docker-compose.prod.yml)

Пример:
  cd ~/apps/exampleProjectSail
  export COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml
  export VPS_DOMAIN=94-228-117-27.sslip.io
  export CERTBOT_EMAIL=you@example.com
  docker compose up -d
  ./scripts/vps-nginx-ssl.sh all
EOF
}

require_sudo() {
    if [[ "${EUID}" -ne 0 ]] && ! sudo -n true 2>/dev/null; then
        echo "Нужны права sudo." >&2
        exit 1
    fi
}

run_root() {
    if [[ "${EUID}" -eq 0 ]]; then
        "$@"
    else
        sudo "$@"
    fi
}

require_domain() {
    if [[ -z "${VPS_DOMAIN}" ]]; then
        echo "Задайте VPS_DOMAIN (например 94-228-117-27.sslip.io)" >&2
        exit 1
    fi
}

require_email() {
    if [[ -z "${CERTBOT_EMAIL}" ]]; then
        echo "Задайте CERTBOT_EMAIL для Let's Encrypt" >&2
        exit 1
    fi
}

compose() {
    (
        cd "${ROOT_DIR}"
        export COMPOSE_FILE
        docker compose "$@"
    )
}

print_nginx_config() {
    require_domain
    cat <<EOF
# ${NGINX_AVAILABLE}
# Host nginx: TLS + proxy → Docker gateway

server {
    listen 80;
    server_name ${VPS_DOMAIN};

    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }

    location / {
        return 301 https://\$host\$request_uri;
    }
}

server {
    listen 443 ssl http2;
    server_name ${VPS_DOMAIN};

    ssl_certificate     /etc/letsencrypt/live/${VPS_DOMAIN}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${VPS_DOMAIN}/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    location / {
        proxy_pass http://127.0.0.1:${GATEWAY_HTTP_PORT};
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
EOF
}

cmd_check() {
    echo "=== Порты 80/443 ==="
    ss -tlnp 2>/dev/null | grep -E ':80 |:443 ' || echo "(ничего не слушает 80/443 — ок для первого certbot standalone)"

    echo
    echo "=== Gateway upstream :${GATEWAY_HTTP_PORT} ==="
    ss -tlnp 2>/dev/null | grep ":${GATEWAY_HTTP_PORT} " || echo "WARNING: gateway не слушает 127.0.0.1:${GATEWAY_HTTP_PORT}"

    echo
    echo "=== system nginx ==="
    systemctl is-active nginx 2>/dev/null || echo "inactive"

    echo
    echo "=== Docker (COMPOSE_FILE=${COMPOSE_FILE}) ==="
    compose ps gateway main-app 2>/dev/null || true

    if [[ -n "${VPS_DOMAIN}" ]]; then
        echo
        echo "=== Сертификат ==="
        if [[ -f "/etc/letsencrypt/live/${VPS_DOMAIN}/fullchain.pem" ]]; then
            echo "OK: /etc/letsencrypt/live/${VPS_DOMAIN}/"
        else
            echo "Нет сертификата для ${VPS_DOMAIN}"
        fi
    fi
}

cmd_install() {
    require_sudo
    echo "Установка nginx и certbot..."
    run_root apt-get update
    run_root DEBIAN_FRONTEND=noninteractive apt-get install -y \
        nginx certbot python3-certbot-nginx
    run_root mkdir -p /var/www/certbot
    run_root systemctl enable nginx
    echo "Готово."
}

stop_docker_public_ports() {
    echo "Останавливаем контейнеры, занимающие 80/443 (main-app, gateway)..."
    compose stop main-app gateway 2>/dev/null || true
    sleep 1
    if ss -tlnp 2>/dev/null | grep -qE '0\.0\.0\.0:(80|443) '; then
        echo "WARNING: порты 80/443 всё ещё заняты:" >&2
        ss -tlnp | grep -E ':80 |:443 ' || true
        echo "Остановите процессы вручную перед issue-cert." >&2
        return 1
    fi
}

start_docker_prod() {
    echo "Запуск Docker (prod overlay)..."
    compose up -d --remove-orphans
}

cmd_issue_cert() {
    require_sudo
    require_domain
    require_email

    stop_docker_public_ports

    echo "Получение сертификата (standalone) для ${VPS_DOMAIN}..."
    if [[ -f "/etc/letsencrypt/live/${VPS_DOMAIN}/fullchain.pem" ]]; then
        echo "Сертификат уже существует, пропуск certonly."
    else
        run_root certbot certonly --standalone \
            -d "${VPS_DOMAIN}" \
            --non-interactive \
            --agree-tos \
            -m "${CERTBOT_EMAIL}"
    fi

    start_docker_prod
    echo "Сертификат получен. Дальше: $(basename "$0") apply-nginx"
}

cmd_apply_nginx() {
    require_sudo
    require_domain

    if [[ ! -f "/etc/letsencrypt/live/${VPS_DOMAIN}/fullchain.pem" ]]; then
        echo "Сначала выполните: $(basename "$0") issue-cert" >&2
        exit 1
    fi

    run_root mkdir -p /var/www/certbot
    print_nginx_config | run_root tee "${NGINX_AVAILABLE}" >/dev/null
    run_root ln -sf "${NGINX_AVAILABLE}" "${NGINX_ENABLED}"
    run_root rm -f /etc/nginx/sites-enabled/default
    run_root rm -f /run/nginx.pid
    run_root nginx -t
    run_root systemctl enable nginx
    run_root systemctl restart nginx
    echo "Nginx настроен: https://${VPS_DOMAIN} → 127.0.0.1:${GATEWAY_HTTP_PORT}"
    echo
    echo "Обновите APP_URL в main-app/.env:"
    echo "  APP_URL=https://${VPS_DOMAIN}"
}

cmd_all() {
    cmd_install
    cmd_issue_cert
    cmd_apply_nginx
    cmd_check
}

main() {
    local action="${1:-check}"

    case "${action}" in
        check)
            cmd_check
            ;;
        install)
            cmd_install
            ;;
        nginx-config)
            print_nginx_config
            ;;
        apply-nginx)
            cmd_apply_nginx
            ;;
        issue-cert)
            cmd_issue_cert
            ;;
        all)
            cmd_all
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
