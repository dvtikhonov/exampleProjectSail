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
YANDEXMAPS_SUBDOMAIN="${YANDEXMAPS_SUBDOMAIN:-yandexmaps}"
YANDEXMAPS_DOMAIN="${YANDEXMAPS_DOMAIN:-}"
URLSHORT_SUBDOMAIN="${URLSHORT_SUBDOMAIN:-urlshort}"
URLSHORT_DOMAIN="${URLSHORT_DOMAIN:-}"
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
  repair        — диагностика + cert (если нет) + apply-nginx
  fix-apt       — починить GPG Docker repo (NO_PUBKEY) и apt update
  install       — apt: nginx, certbot, python3-certbot-nginx
  nginx-config  — вывести конфиг для /etc/nginx/sites-available/
  apply-nginx   — записать конфиг и reload nginx (нужен sudo)
  issue-cert    — certbot certonly --standalone (освобождает 80/443)
  issue-cert-maps — расширить существующий сертификат для субдоменов yandexmaps.* и urlshort.*
  all           — install → issue-cert → apply-nginx → check

Переменные:
  VPS_DOMAIN          публичный домен (обязательно для cert/nginx)
  YANDEXMAPS_SUBDOMAIN  префикс субдомена service-d (по умолчанию: yandexmaps)
  YANDEXMAPS_DOMAIN   полный субдомен (по умолчанию: \${YANDEXMAPS_SUBDOMAIN}.\${VPS_DOMAIN})
  URLSHORT_SUBDOMAIN    префикс субдомена service-f (по умолчанию: urlshort)
  URLSHORT_DOMAIN     полный субдомен (по умолчанию: \${URLSHORT_SUBDOMAIN}.\${VPS_DOMAIN})
  CERTBOT_EMAIL       email для Let's Encrypt (обязательно для issue-cert/all)
  GATEWAY_HTTP_PORT   upstream Docker gateway (по умолчанию: 8080)
  NGINX_SITE_NAME     имя файла в sites-available (по умолчанию: exampleprojectsail)
  COMPOSE_FILE        overlay compose (по умолчанию: docker-compose.yml:docker-compose.prod.yml)

Перед выпуском сертификата добавьте DNS A-записи для субдоменов:
  \${YANDEXMAPS_SUBDOMAIN}.\${VPS_DOMAIN} → IP VPS (тот же, что и у \${VPS_DOMAIN})
  \${URLSHORT_SUBDOMAIN}.\${VPS_DOMAIN} → IP VPS (тот же, что и у \${VPS_DOMAIN})

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

detect_sslip_domain() {
    local ip hyphenated
    ip="$(hostname -I 2>/dev/null | awk '{print $1}')"
    [[ -n "${ip}" ]] || return 1
    hyphenated="${ip//./-}"
    echo "${hyphenated}.sslip.io"
}

ensure_domain() {
    if [[ -z "${VPS_DOMAIN}" ]]; then
        VPS_DOMAIN="$(detect_sslip_domain || true)"
        if [[ -n "${VPS_DOMAIN}" ]]; then
            echo "VPS_DOMAIN не задан — использую ${VPS_DOMAIN}"
        fi
    fi
    require_domain
    resolve_yandexmaps_domain
    resolve_urlshort_domain
}

resolve_yandexmaps_domain() {
    if [[ -z "${YANDEXMAPS_DOMAIN}" ]]; then
        YANDEXMAPS_DOMAIN="${YANDEXMAPS_SUBDOMAIN}.${VPS_DOMAIN}"
    fi
}

resolve_urlshort_domain() {
    if [[ -z "${URLSHORT_DOMAIN}" ]]; then
        URLSHORT_DOMAIN="${URLSHORT_SUBDOMAIN}.${VPS_DOMAIN}"
    fi
}

ports_80_443_listeners() {
    ss -tlnp 2>/dev/null | grep -E ':(80|443) ' || true
}

ports_80_443_busy() {
    ss -tlnp 2>/dev/null | grep -qE ':(80|443) '
}

cert_path() {
    echo "/etc/letsencrypt/live/${VPS_DOMAIN}/fullchain.pem"
}

cert_exists() {
    local domain="${1:-${VPS_DOMAIN}}"
    [[ -n "${domain}" ]] || return 1
    if [[ "${EUID}" -eq 0 ]]; then
        [[ -f "/etc/letsencrypt/live/${domain}/fullchain.pem" ]]
    else
        sudo test -f "/etc/letsencrypt/live/${domain}/fullchain.pem"
    fi
}

print_nginx_config() {
    require_domain
    resolve_yandexmaps_domain
    resolve_urlshort_domain
    cat <<EOF
# ${NGINX_AVAILABLE}
# Host nginx: TLS + proxy → Docker gateway
# Основной домен → main-app; ${YANDEXMAPS_DOMAIN} → service-d; ${URLSHORT_DOMAIN} → service-f (Host-based routing в nginx-gateway)

server {
    listen 80;
    server_name ${VPS_DOMAIN} ${YANDEXMAPS_DOMAIN} ${URLSHORT_DOMAIN};

    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }

    location / {
        return 301 https://\$host\$request_uri;
    }
}

server {
    listen 443 ssl http2;
    server_name ${VPS_DOMAIN} ${YANDEXMAPS_DOMAIN} ${URLSHORT_DOMAIN};

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
    ensure_domain 2>/dev/null || true

    echo "=== Порты 80/443 ==="
    local listeners
    listeners="$(ports_80_443_listeners)"
    if [[ -z "${listeners}" ]]; then
        echo "НИКТО не слушает 80/443 → curl: Connection refused"
        echo "  Нужно: sudo ./scripts/vps-nginx-ssl.sh repair"
    else
        echo "${listeners}"
    fi

    echo
    echo "=== Gateway upstream :${GATEWAY_HTTP_PORT} ==="
    if ss -tlnp 2>/dev/null | grep -q ":${GATEWAY_HTTP_PORT} "; then
        ss -tlnp 2>/dev/null | grep ":${GATEWAY_HTTP_PORT} "
    else
        echo "WARNING: gateway не слушает 127.0.0.1:${GATEWAY_HTTP_PORT}"
        echo "  export COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml"
        echo "  docker compose up -d gateway"
    fi

    echo
    echo "=== system nginx ==="
    if systemctl is-active nginx >/dev/null 2>&1; then
        systemctl status nginx --no-pager -l | head -5
    else
        echo "inactive (dead) — HTTPS не работает"
    fi

    echo
    echo "=== Docker (COMPOSE_FILE=${COMPOSE_FILE}) ==="
    compose ps gateway main-app 2>/dev/null || true

    if [[ -n "${VPS_DOMAIN}" ]]; then
        echo
        echo "=== Сертификат (${VPS_DOMAIN}) ==="
        if cert_exists; then
            echo "OK: /etc/letsencrypt/live/${VPS_DOMAIN}/"
        else
            echo "Нет сертификата — нужен: ./scripts/vps-nginx-ssl.sh issue-cert"
        fi

        resolve_yandexmaps_domain 2>/dev/null || true
        resolve_urlshort_domain 2>/dev/null || true

        echo
        echo "=== Субдомен service-d (${YANDEXMAPS_DOMAIN:-—}) ==="
        if [[ -n "${YANDEXMAPS_DOMAIN:-}" ]]; then
            echo "DNS: A-запись ${YANDEXMAPS_DOMAIN} → IP VPS (тот же, что ${VPS_DOMAIN})"
            curl -s -o /dev/null -w "HTTPS ${YANDEXMAPS_DOMAIN} → %{http_code}\n" --connect-timeout 2 "https://${YANDEXMAPS_DOMAIN}/" 2>/dev/null \
                || echo "HTTPS субдомена недоступен (DNS / cert / nginx-gateway Host routing)"
        fi

        echo
        echo "=== Субдомен service-f (${URLSHORT_DOMAIN:-—}) ==="
        if [[ -n "${URLSHORT_DOMAIN:-}" ]]; then
            echo "DNS: A-запись ${URLSHORT_DOMAIN} → IP VPS (тот же, что ${VPS_DOMAIN})"
            curl -s -o /dev/null -w "HTTPS ${URLSHORT_DOMAIN} → %{http_code}\n" --connect-timeout 2 "https://${URLSHORT_DOMAIN}/" 2>/dev/null \
                || echo "HTTPS субдомена недоступен (DNS / cert / nginx-gateway Host routing)"
        fi

        echo
        echo "=== Локальная проверка ==="
        curl -s -o /dev/null -w "HTTPS ${VPS_DOMAIN} → %{http_code}\n" --connect-timeout 2 "https://${VPS_DOMAIN}/" 2>/dev/null \
            || echo "HTTPS недоступен (connection refused / cert / nginx)"
    fi
}

cmd_install() {
    require_sudo
    echo "Установка nginx и certbot..."

    if ! apt_get_update_safe; then
        echo "WARNING: apt-get update не удался — пробую установить из локального кэша apt..." >&2
    fi

    if ! run_root DEBIAN_FRONTEND=noninteractive apt-get install -y \
        nginx certbot python3-certbot-nginx; then
        echo "Повтор install после fix-apt..." >&2
        cmd_fix_apt
        run_root DEBIAN_FRONTEND=noninteractive apt-get install -y \
            nginx certbot python3-certbot-nginx
    fi

    run_root mkdir -p /var/www/certbot
    run_root systemctl enable nginx
    echo "Готово."
}

fix_docker_apt_gpg() {
    echo "Импорт GPG-ключа Docker (7EA0A9C3F273FCD8)..."
    run_root apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys 7EA0A9C3F273FCD8 2>/dev/null \
        || run_root apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 7EA0A9C3F273FCD8
}

apt_get_update_safe() {
    if run_root apt-get update; then
        return 0
    fi

    echo "WARNING: apt-get update failed — типичная причина: NO_PUBKEY Docker repo." >&2
    if [[ -f /etc/apt/sources.list.d/docker.list ]] \
        || [[ -f /etc/apt/sources.list.d/docker-ce.list ]]; then
        fix_docker_apt_gpg || true
        run_root apt-get update && return 0
    fi

    return 1
}

cmd_fix_apt() {
    require_sudo

    if [[ -f /etc/apt/sources.list.d/docker.list ]] \
        || [[ -f /etc/apt/sources.list.d/docker-ce.list ]]; then
        fix_docker_apt_gpg
    else
        echo "Docker apt repo не найден — проверьте /etc/apt/sources.list.d/" >&2
    fi

    run_root apt-get update
    echo "apt update OK."
}

stop_docker_public_ports() {
    echo "Останавливаем контейнеры, занимающие 80/443 (main-app, gateway)..."
    compose stop main-app gateway 2>/dev/null || true
    sleep 1
    if ports_80_443_busy; then
        echo "WARNING: порты 80/443 всё ещё заняты:" >&2
        ports_80_443_listeners >&2
        echo "Остановите процессы вручную перед issue-cert." >&2
        return 1
    fi
}

start_docker_prod() {
    echo "Запуск Docker (prod overlay)..."
    compose up -d --remove-orphans
}

run_certbot_standalone() {
    local expand_flag=()
    if cert_exists; then
        expand_flag=(--expand)
        echo "Расширение существующего сертификата для ${YANDEXMAPS_DOMAIN} и ${URLSHORT_DOMAIN}..."
    else
        echo "Получение сертификата (standalone) для ${VPS_DOMAIN}, ${YANDEXMAPS_DOMAIN} и ${URLSHORT_DOMAIN}..."
    fi

    run_root certbot certonly --standalone \
        "${expand_flag[@]}" \
        -d "${VPS_DOMAIN}" \
        -d "${YANDEXMAPS_DOMAIN}" \
        -d "${URLSHORT_DOMAIN}" \
        --non-interactive \
        --agree-tos \
        -m "${CERTBOT_EMAIL}"
}

cmd_issue_cert() {
    require_sudo
    require_domain
    resolve_yandexmaps_domain
    resolve_urlshort_domain
    require_email

    stop_docker_public_ports

    echo "DNS: перед certbot убедитесь, что A-записи указывают на этот VPS:"
    echo "  ${VPS_DOMAIN}"
    echo "  ${YANDEXMAPS_DOMAIN}"
    echo "  ${URLSHORT_DOMAIN}"

    if cert_exists; then
        echo "Сертификат для ${VPS_DOMAIN} уже существует."
        echo "Чтобы добавить субдомены: $(basename "$0") issue-cert-maps"
    else
        run_certbot_standalone
    fi

    start_docker_prod
    echo "Сертификат получен. Дальше: $(basename "$0") apply-nginx"
}

cmd_issue_cert_maps() {
    require_sudo
    require_domain
    resolve_yandexmaps_domain
    resolve_urlshort_domain
    require_email

    if ! cert_exists; then
        echo "Сертификат для ${VPS_DOMAIN} не найден — сначала: $(basename "$0") issue-cert" >&2
        exit 1
    fi

    stop_docker_public_ports

    echo "DNS: A-записи ${YANDEXMAPS_DOMAIN} и ${URLSHORT_DOMAIN} → IP VPS"
    run_certbot_standalone

    start_docker_prod
    echo "Сертификат расширен. Дальше: $(basename "$0") apply-nginx"
}

cmd_apply_nginx() {
    require_sudo
    ensure_domain

    if ! cert_exists; then
        echo "Сначала выполните: $(basename "$0") issue-cert" >&2
        echo "(файл $(cert_path) недоступен без sudo или cert ещё не выпущен)" >&2
        exit 1
    fi

    if ! ss -tlnp 2>/dev/null | grep -q ":${GATEWAY_HTTP_PORT} "; then
        echo "Gateway не слушает 127.0.0.1:${GATEWAY_HTTP_PORT} — поднимаю Docker..." >&2
        start_docker_prod
        sleep 2
        if ! ss -tlnp 2>/dev/null | grep -q ":${GATEWAY_HTTP_PORT} "; then
            echo "ERROR: gateway всё ещё недоступен на :${GATEWAY_HTTP_PORT}" >&2
            exit 1
        fi
    fi

    run_root mkdir -p /var/www/certbot
    print_nginx_config | run_root tee "${NGINX_AVAILABLE}" >/dev/null
    run_root ln -sf "${NGINX_AVAILABLE}" "${NGINX_ENABLED}"
    run_root rm -f /etc/nginx/sites-enabled/default
    run_root rm -f /run/nginx.pid
    run_root nginx -t
    run_root systemctl enable nginx
    run_root systemctl restart nginx

    if ! systemctl is-active nginx >/dev/null 2>&1; then
        echo "ERROR: nginx не запустился. Лог:" >&2
        run_root journalctl -u nginx --no-pager -n 20 >&2
        exit 1
    fi

    resolve_yandexmaps_domain
    resolve_urlshort_domain

    echo "Nginx настроен:"
    echo "  https://${VPS_DOMAIN} → 127.0.0.1:${GATEWAY_HTTP_PORT} (main-app)"
    echo "  https://${YANDEXMAPS_DOMAIN} → 127.0.0.1:${GATEWAY_HTTP_PORT} (service-d, Host routing в gateway)"
    echo "  https://${URLSHORT_DOMAIN} → 127.0.0.1:${GATEWAY_HTTP_PORT} (service-f, Host routing в gateway)"
    echo
    echo "Обновите main-app/.env:"
    echo "  APP_URL=https://${VPS_DOMAIN}"
    echo
    echo "Обновите service-d/.env (production):"
    echo "  APP_URL=https://${YANDEXMAPS_DOMAIN}"
    echo "  SANCTUM_STATEFUL_DOMAINS=${YANDEXMAPS_DOMAIN}"
    echo "  SESSION_DOMAIN=${YANDEXMAPS_DOMAIN}"
    echo
    echo "Обновите service-f/.env (production):"
    echo "  APP_URL=https://${URLSHORT_DOMAIN}"
}

cmd_repair() {
    ensure_domain
    cmd_check
    echo
    echo "=== Repair ==="

    if ! command -v nginx >/dev/null 2>&1; then
        cmd_install
    fi

    if ! cert_exists; then
        require_email
        cmd_issue_cert
    else
        start_docker_prod
    fi

    cmd_apply_nginx
    cmd_check
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
        fix-apt)
            cmd_fix_apt
            ;;
        nginx-config)
            print_nginx_config
            ;;
        apply-nginx)
            cmd_apply_nginx
            ;;
        repair)
            cmd_repair
            ;;
        issue-cert)
            cmd_issue_cert
            ;;
        issue-cert-maps)
            cmd_issue_cert_maps
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
