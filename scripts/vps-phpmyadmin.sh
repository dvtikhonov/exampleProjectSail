#!/usr/bin/env bash
# phpMyAdmin на VPS: host nginx (HTTPS + Basic Auth) → Docker phpMyAdmin (127.0.0.1:8085).
#
# Запускать НА VPS из корня репозитория после:
#   export COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml
#   ./scripts/vps-phpmyadmin-mysql.sh apply
#   docker compose up -d phpmyadmin
#
# Пример (sslip.io):
#   export VPS_DOMAIN=94-228-117-27.sslip.io
#   export CERTBOT_EMAIL=admin@example.com
#   ./scripts/vps-phpmyadmin.sh create-htpasswd
#   ./scripts/vps-phpmyadmin.sh all

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${ROOT_DIR}/.env"

VPS_DOMAIN="${VPS_DOMAIN:-}"
PMA_SUBDOMAIN="${PMA_SUBDOMAIN:-pma}"
PMA_DOMAIN="${PMA_DOMAIN:-}"
CERTBOT_EMAIL="${CERTBOT_EMAIL:-}"
PHPMYADMIN_PORT="${PHPMYADMIN_PORT:-8085}"
YANDEXMAPS_SUBDOMAIN="${YANDEXMAPS_SUBDOMAIN:-yandexmaps}"
YANDEXMAPS_DOMAIN="${YANDEXMAPS_DOMAIN:-}"
NGINX_SITE_NAME="${NGINX_SITE_NAME:-pma}"
HTPASSWD_FILE="${HTPASSWD_FILE:-/etc/nginx/.htpasswd-phpmyadmin}"
NGINX_AVAILABLE="/etc/nginx/sites-available/${NGINX_SITE_NAME}"
NGINX_ENABLED="/etc/nginx/sites-enabled/${NGINX_SITE_NAME}"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.yml:docker-compose.prod.yml}"

usage() {
    cat <<EOF
Использование: $(basename "$0") <команда>

Команды:
  check           — порт phpMyAdmin, сертификат, nginx site, curl
  install-deps    — apt: apache2-utils (htpasswd)
  create-htpasswd — создать/дополнить ${HTPASSWD_FILE} (интерактивно)
  nginx-config    — вывести конфиг для /etc/nginx/sites-available/${NGINX_SITE_NAME}
  apply-nginx     — записать site pma и reload nginx (нужен sudo)
  issue-cert      — certbot --expand: добавить pma.\${VPS_DOMAIN} к существующему сертификату
  all             — install-deps → issue-cert → apply-nginx → check
                    (create-htpasswd — отдельно, если файла ещё нет)

Переменные (корневой .env или export):
  VPS_DOMAIN          публичный домен (обязательно)
  PMA_SUBDOMAIN       префикс субдомена (по умолчанию: pma)
  PMA_DOMAIN          полный субдомен (по умолчанию: \${PMA_SUBDOMAIN}.\${VPS_DOMAIN})
  PHPMYADMIN_PORT     upstream Docker phpMyAdmin (по умолчанию: 8085)
  CERTBOT_EMAIL       email Let's Encrypt (для issue-cert, если cert ещё нет)
  HTPASSWD_FILE       файл Basic Auth (по умолчанию: /etc/nginx/.htpasswd-phpmyadmin)
  NGINX_SITE_NAME     имя site в sites-available (по умолчанию: pma)
  COMPOSE_FILE        overlay compose (по умолчанию: docker-compose.yml:docker-compose.prod.yml)

Перед issue-cert нужен сертификат для \${VPS_DOMAIN}:
  ./scripts/vps-nginx-ssl.sh all

Пример:
  cd ~/apps/exampleProjectSail
  export COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml
  export VPS_DOMAIN=94-228-117-27.sslip.io
  export CERTBOT_EMAIL=you@example.com
  docker compose up -d phpmyadmin
  ./scripts/vps-phpmyadmin.sh create-htpasswd
  ./scripts/vps-phpmyadmin.sh all
  ./scripts/vps-phpmyadmin.sh check
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

load_env() {
    if [[ -f "${ENV_FILE}" ]]; then
        # shellcheck disable=SC1090
        set -a
        source "${ENV_FILE}"
        set +a
    fi

    VPS_DOMAIN="${VPS_DOMAIN%$'\r'}"
    PMA_SUBDOMAIN="${PMA_SUBDOMAIN:-pma}"
    PMA_SUBDOMAIN="${PMA_SUBDOMAIN%$'\r'}"
    PMA_DOMAIN="${PMA_DOMAIN%$'\r'}"
    CERTBOT_EMAIL="${CERTBOT_EMAIL%$'\r'}"
    PHPMYADMIN_PORT="${PHPMYADMIN_PORT:-8085}"
    PHPMYADMIN_PORT="${PHPMYADMIN_PORT%$'\r'}"
    YANDEXMAPS_SUBDOMAIN="${YANDEXMAPS_SUBDOMAIN:-yandexmaps}"
    YANDEXMAPS_SUBDOMAIN="${YANDEXMAPS_SUBDOMAIN%$'\r'}"
    YANDEXMAPS_DOMAIN="${YANDEXMAPS_DOMAIN%$'\r'}"
}

require_domain() {
    if [[ -z "${VPS_DOMAIN}" ]]; then
        echo "Задайте VPS_DOMAIN (например 94-228-117-27.sslip.io)" >&2
        exit 1
    fi
}

require_email_if_no_cert() {
    if ! cert_exists && [[ -z "${CERTBOT_EMAIL}" ]]; then
        echo "Задайте CERTBOT_EMAIL для первичного выпуска сертификата Let's Encrypt" >&2
        exit 1
    fi
}

detect_sslip_domain() {
    local ip hyphenated
    ip="$(hostname -I 2>/dev/null | awk '{print $1}')"
    [[ -n "${ip}" ]] || return 1
    hyphenated="${ip//./-}"
    echo "${hyphenated}.sslip.io"
}

resolve_pma_domain() {
    if [[ -z "${PMA_DOMAIN}" ]]; then
        PMA_DOMAIN="${PMA_SUBDOMAIN}.${VPS_DOMAIN}"
    fi
}

resolve_yandexmaps_domain() {
    if [[ -z "${YANDEXMAPS_DOMAIN}" ]]; then
        YANDEXMAPS_DOMAIN="${YANDEXMAPS_SUBDOMAIN}.${VPS_DOMAIN}"
    fi
}

ensure_domain() {
    load_env
    if [[ -z "${VPS_DOMAIN}" ]]; then
        VPS_DOMAIN="$(detect_sslip_domain || true)"
        if [[ -n "${VPS_DOMAIN}" ]]; then
            echo "VPS_DOMAIN не задан — использую ${VPS_DOMAIN}"
        fi
    fi
    require_domain
    resolve_pma_domain
    resolve_yandexmaps_domain
}

compose() {
    (
        cd "${ROOT_DIR}"
        export COMPOSE_FILE
        docker compose "$@"
    )
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

cert_includes_domain() {
    local domain="$1"
    local text

    if [[ "${EUID}" -ne 0 ]]; then
        text="$(sudo openssl x509 -in "/etc/letsencrypt/live/${VPS_DOMAIN}/cert.pem" -noout -text 2>/dev/null || true)"
    else
        text="$(openssl x509 -in "/etc/letsencrypt/live/${VPS_DOMAIN}/cert.pem" -noout -text 2>/dev/null || true)"
    fi

    [[ -n "${text}" ]] || return 1
    grep -qF "DNS:${domain}" <<< "${text}"
}

collect_cert_domains() {
    local cert_pem="/etc/letsencrypt/live/${VPS_DOMAIN}/cert.pem"
    local text

    if [[ "${EUID}" -ne 0 ]]; then
        text="$(sudo openssl x509 -in "${cert_pem}" -noout -text 2>/dev/null || true)"
    else
        text="$(openssl x509 -in "${cert_pem}" -noout -text 2>/dev/null || true)"
    fi

    [[ -n "${text}" ]] || return 1

    printf '%s\n' "${text}" | sed -n 's/[[:space:]]*DNS://p' | sort -u
}

build_cert_domain_flags() {
    local -a domains=()
    local domain

    while IFS= read -r domain; do
        [[ -n "${domain}" ]] || continue
        domains+=("${domain}")
    done < <(collect_cert_domains || true)

    if [[ ${#domains[@]} -eq 0 ]]; then
        domains=("${VPS_DOMAIN}")
        if [[ -n "${YANDEXMAPS_DOMAIN}" ]]; then
            domains+=("${YANDEXMAPS_DOMAIN}")
        fi
    fi

    if ! printf '%s\n' "${domains[@]}" | grep -qx "${PMA_DOMAIN}"; then
        domains+=("${PMA_DOMAIN}")
    fi

    CERT_DOMAIN_FLAGS=()
    for domain in "${domains[@]}"; do
        CERT_DOMAIN_FLAGS+=(-d "${domain}")
    done
}

ports_80_443_busy() {
    ss -tlnp 2>/dev/null | grep -qE ':(80|443) '
}

htpasswd_exists() {
    if [[ "${EUID}" -eq 0 ]]; then
        [[ -f "${HTPASSWD_FILE}" ]]
    else
        sudo test -f "${HTPASSWD_FILE}"
    fi
}

print_nginx_config() {
    require_domain
    resolve_pma_domain
    cat <<EOF
# ${NGINX_AVAILABLE}
# phpMyAdmin: TLS + HTTP Basic Auth → Docker phpMyAdmin (127.0.0.1:${PHPMYADMIN_PORT})

server {
    listen 80;
    server_name ${PMA_DOMAIN};

    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }

    location / {
        return 301 https://\$host\$request_uri;
    }
}

server {
    listen 443 ssl http2;
    server_name ${PMA_DOMAIN};

    ssl_certificate     /etc/letsencrypt/live/${VPS_DOMAIN}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${VPS_DOMAIN}/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    auth_basic "phpMyAdmin";
    auth_basic_user_file ${HTPASSWD_FILE};

    location / {
        proxy_pass http://127.0.0.1:${PHPMYADMIN_PORT};
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 300;
    }
}
EOF
}

cmd_install_deps() {
    require_sudo
    echo "Установка apache2-utils (htpasswd)..."

    if ! run_root apt-get update; then
        echo "WARNING: apt-get update не удался — пробую установить из кэша..." >&2
    fi

    run_root DEBIAN_FRONTEND=noninteractive apt-get install -y apache2-utils
    echo "Готово."
}

cmd_create_htpasswd() {
    require_sudo

    if ! command -v htpasswd >/dev/null 2>&1; then
        echo "htpasswd не найден — выполните: $(basename "$0") install-deps" >&2
        exit 1
    fi

    local username="${HTPASSWD_USER:-}"
    if [[ -z "${username}" ]]; then
        read -r -p "Имя пользователя Basic Auth [pma]: " username
        username="${username:-pma}"
    fi

    echo "Файл: ${HTPASSWD_FILE}"
    if htpasswd_exists; then
        echo "Файл уже существует — будет добавлен/обновлён пользователь ${username}."
        run_root htpasswd "${HTPASSWD_FILE}" "${username}"
    else
        echo "Создание нового файла (интерактивный ввод пароля)."
        run_root htpasswd -c "${HTPASSWD_FILE}" "${username}"
    fi

    run_root chmod 640 "${HTPASSWD_FILE}"
    run_root chown root:www-data "${HTPASSWD_FILE}" 2>/dev/null \
        || run_root chown root:nginx "${HTPASSWD_FILE}" 2>/dev/null \
        || true

    echo "Basic Auth настроен: ${HTPASSWD_FILE}"
}

stop_nginx_for_cert() {
    echo "Останавливаем nginx для certbot standalone..."
    run_root systemctl stop nginx 2>/dev/null || true
    sleep 1

    if ports_80_443_busy; then
        echo "WARNING: порты 80/443 всё ещё заняты:" >&2
        ss -tlnp 2>/dev/null | grep -E ':(80|443) ' >&2 || true
        echo "Освободите 80/443 перед issue-cert." >&2
        return 1
    fi
}

start_nginx() {
    run_root systemctl start nginx 2>/dev/null || true
}

cmd_issue_cert() {
    require_sudo
    ensure_domain
    require_email_if_no_cert

    if ! cert_exists; then
        echo "Сертификат для ${VPS_DOMAIN} не найден." >&2
        echo "Сначала выполните: ./scripts/vps-nginx-ssl.sh all" >&2
        exit 1
    fi

    if cert_includes_domain "${PMA_DOMAIN}"; then
        echo "Сертификат уже содержит ${PMA_DOMAIN} — расширение не требуется."
        return 0
    fi

    build_cert_domain_flags

    echo "Расширение сертификата ${VPS_DOMAIN} для субдомена ${PMA_DOMAIN}..."
    echo "Домены в новом cert:"
    local i=1
    while [[ $i -lt ${#CERT_DOMAIN_FLAGS[@]} ]]; do
        echo "  - ${CERT_DOMAIN_FLAGS[$i]}"
        i=$((i + 2))
    done

    stop_nginx_for_cert

    local certbot_args=(
        certonly
        --standalone
        --expand
        --cert-name "${VPS_DOMAIN}"
        "${CERT_DOMAIN_FLAGS[@]}"
        --non-interactive
        --agree-tos
    )

    if [[ -n "${CERTBOT_EMAIL}" ]]; then
        certbot_args+=(-m "${CERTBOT_EMAIL}")
    fi

    if ! run_root certbot "${certbot_args[@]}"; then
        start_nginx
        echo "ERROR: certbot не смог расширить сертификат." >&2
        exit 1
    fi

    start_nginx
    echo "Сертификат расширен. Дальше: $(basename "$0") apply-nginx"
}

cmd_apply_nginx() {
    require_sudo
    ensure_domain

    if ! cert_exists; then
        echo "Сначала выполните: ./scripts/vps-nginx-ssl.sh all && $(basename "$0") issue-cert" >&2
        exit 1
    fi

    if ! cert_includes_domain "${PMA_DOMAIN}"; then
        echo "Сертификат не содержит ${PMA_DOMAIN} — выполните: $(basename "$0") issue-cert" >&2
        exit 1
    fi

    if ! htpasswd_exists; then
        echo "Файл Basic Auth не найден: ${HTPASSWD_FILE}" >&2
        echo "Выполните: $(basename "$0") create-htpasswd" >&2
        exit 1
    fi

    if ! ss -tlnp 2>/dev/null | grep -q ":${PHPMYADMIN_PORT} "; then
        echo "phpMyAdmin не слушает 127.0.0.1:${PHPMYADMIN_PORT} — поднимаю контейнер..." >&2
        compose up -d phpmyadmin
        sleep 2
        if ! ss -tlnp 2>/dev/null | grep -q ":${PHPMYADMIN_PORT} "; then
            echo "ERROR: phpMyAdmin недоступен на 127.0.0.1:${PHPMYADMIN_PORT}" >&2
            echo "  export COMPOSE_FILE=${COMPOSE_FILE}" >&2
            echo "  docker compose up -d phpmyadmin" >&2
            exit 1
        fi
    fi

    run_root mkdir -p /var/www/certbot
    print_nginx_config | run_root tee "${NGINX_AVAILABLE}" >/dev/null
    run_root ln -sf "${NGINX_AVAILABLE}" "${NGINX_ENABLED}"
    run_root nginx -t
    run_root systemctl enable nginx
    run_root systemctl reload nginx

    if ! systemctl is-active nginx >/dev/null 2>&1; then
        echo "ERROR: nginx не активен. Лог:" >&2
        run_root journalctl -u nginx --no-pager -n 20 >&2
        exit 1
    fi

    echo "Nginx site ${NGINX_SITE_NAME} настроен:"
    echo "  https://${PMA_DOMAIN} → 127.0.0.1:${PHPMYADMIN_PORT} (Basic Auth + pma_admin в phpMyAdmin)"
}

cmd_check() {
    ensure_domain 2>/dev/null || true
    load_env
    resolve_pma_domain 2>/dev/null || true

    echo "=== phpMyAdmin upstream :${PHPMYADMIN_PORT} ==="
    if ss -tlnp 2>/dev/null | grep -q ":${PHPMYADMIN_PORT} "; then
        ss -tlnp 2>/dev/null | grep ":${PHPMYADMIN_PORT} "
    else
        echo "WARNING: phpMyAdmin не слушает 127.0.0.1:${PHPMYADMIN_PORT}"
        echo "  export COMPOSE_FILE=${COMPOSE_FILE}"
        echo "  docker compose up -d phpmyadmin"
    fi

    echo
    echo "=== Docker phpmyadmin (COMPOSE_FILE=${COMPOSE_FILE}) ==="
    compose ps phpmyadmin 2>/dev/null || true

    echo
    echo "=== system nginx site ${NGINX_SITE_NAME} ==="
    if [[ -L "${NGINX_ENABLED}" || -f "${NGINX_ENABLED}" ]]; then
        echo "OK: ${NGINX_ENABLED}"
    elif [[ "${EUID}" -eq 0 ]] && [[ -e "${NGINX_ENABLED}" ]]; then
        echo "OK: ${NGINX_ENABLED}"
    elif sudo -n test -e "${NGINX_ENABLED}" 2>/dev/null; then
        echo "OK: ${NGINX_ENABLED}"
    else
        echo "Site не включён — нужен: $(basename "$0") apply-nginx"
    fi

    echo
    echo "=== Basic Auth (${HTPASSWD_FILE}) ==="
    if htpasswd_exists; then
        echo "OK: файл существует"
    else
        echo "Нет файла — нужен: $(basename "$0") create-htpasswd"
    fi

    if [[ -n "${VPS_DOMAIN}" ]]; then
        resolve_pma_domain

        echo
        echo "=== Сертификат (${VPS_DOMAIN}) ==="
        if cert_exists; then
            echo "OK: /etc/letsencrypt/live/${VPS_DOMAIN}/"
            if cert_includes_domain "${PMA_DOMAIN}"; then
                echo "OK: SAN содержит ${PMA_DOMAIN}"
            else
                echo "WARNING: SAN не содержит ${PMA_DOMAIN} — нужен: $(basename "$0") issue-cert"
            fi
        else
            echo "Нет сертификата — сначала: ./scripts/vps-nginx-ssl.sh all"
        fi

        echo
        echo "=== HTTPS ${PMA_DOMAIN} ==="
        local http_code
        http_code="$(curl -s -o /dev/null -w '%{http_code}' --connect-timeout 3 "https://${PMA_DOMAIN}/" 2>/dev/null || echo "000")"
        case "${http_code}" in
            401)
                echo "HTTPS ${PMA_DOMAIN} → ${http_code} (ожидаемо без Basic Auth)"
                ;;
            200|302|303)
                echo "HTTPS ${PMA_DOMAIN} → ${http_code} (доступ без Basic Auth — проверьте nginx site)"
                ;;
            000)
                echo "HTTPS ${PMA_DOMAIN} недоступен (DNS / cert / nginx / phpMyAdmin)"
                ;;
            *)
                echo "HTTPS ${PMA_DOMAIN} → ${http_code}"
                ;;
        esac

        if [[ -n "${HTPASSWD_USER:-}" && -n "${HTPASSWD_PASSWORD:-}" ]]; then
            http_code="$(curl -s -o /dev/null -w '%{http_code}' --connect-timeout 3 \
                -u "${HTPASSWD_USER}:${HTPASSWD_PASSWORD}" \
                "https://${PMA_DOMAIN}/" 2>/dev/null || echo "000")"
            echo "С Basic Auth (${HTPASSWD_USER}): ${http_code}"
        else
            echo "Для проверки с Basic Auth задайте HTPASSWD_USER и HTPASSWD_PASSWORD (export)."
        fi
    fi
}

cmd_all() {
    cmd_install_deps

    if ! htpasswd_exists; then
        echo "WARNING: ${HTPASSWD_FILE} не найден — пропускаю create-htpasswd (интерактивно)." >&2
        echo "Выполните: $(basename "$0") create-htpasswd" >&2
    fi

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
        install-deps)
            cmd_install_deps
            ;;
        create-htpasswd)
            cmd_create_htpasswd
            ;;
        nginx-config)
            load_env
            ensure_domain
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
