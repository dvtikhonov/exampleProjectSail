#!/usr/bin/env bash
# HTTP-туннель для service-b или service-c через fxTunnel.
# Регистрация и токен: https://fxtun.dev (зеркало: https://fxtun.ru)

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TUNNEL_SERVICE="${TUNNEL_SERVICE:-service-b}"
PORT="${SERVICE_B_PORT:-8082}"
DOCKER_SERVICE="service-b"

if [[ "${1:-}" == "service-c" || "${TUNNEL_SERVICE}" == "service-c" ]]; then
    TUNNEL_SERVICE="service-c"
    PORT="${SERVICE_C_PORT:-8083}"
    DOCKER_SERVICE="service-c"
fi

LOCAL_URL="http://127.0.0.1:${PORT}"
BIN_DIR="${HOME}/.local/bin"
FXTUN_BIN="${FXTUN_BIN:-fxtun}"
FXTUN_SERVER="${FXTUN_SERVER:-fxtun.dev}"
FXTUN_PUBLIC_DOMAIN="${FXTUN_PUBLIC_DOMAIN:-fxtun.dev}"
FXTUN_CONNECT_TIMEOUT="${FXTUN_CONNECT_TIMEOUT:-25}"
FXTUN_SERVER_IP="${FXTUN_SERVER_IP:-95.181.173.114}"
INSTALL_URL="${FXTUN_INSTALL_URL:-https://fxtun.dev/install.sh}"
GITHUB_RELEASE="${FXTUN_GITHUB_RELEASE:-https://github.com/mephistofox/fxtun.dev/releases/download/v3.3.0/fxtunnel-linux-amd64}"

usage() {
    cat <<EOF
Использование: $(basename "$0") [service-b|service-c] [install|run|check]

  install  — установить CLI fxtun
  run      — запустить HTTP-туннель (по умолчанию service-b:8082)
  check    — проверить доступность серверов fxTunnel и локального сервиса

  service-c — туннель на service-c (порт 8083) для MAX webhook / mini-app

Переменные:
  TUNNEL_SERVICE        service-b (по умолчанию) или service-c
  SERVICE_B_PORT        порт service-b (по умолчанию: 8082)
  SERVICE_C_PORT        порт service-c (по умолчанию: 8083)
  FXTUN_TOKEN           API-токен (sk_...) — обязателен для run
  FXTUN_DOMAIN          свой субдомен (опционально)
  FXTUN_SERVER          хост сервера (по умолчанию: fxtun.dev)
  FXTUN_PUBLIC_DOMAIN   домен в публичном URL (по умолчанию: fxtun.dev)
  FXTUN_CONNECT_TIMEOUT таймаут curl/nc в секундах (по умолчанию: 25; в WSL DNS часто медленный)
  FXTUN_SERVER_IP       IP fxtun.dev для обхода DNS в WSL (по умолчанию: 95.181.173.114)
  FXTUN_SKIP_PREFLIGHT  1 — пропустить проверку доступности сервера
  FXTUN_BIN             путь к бинарнику fxtun / fxtunnel
  FXTUN_INSTALL_URL     URL install.sh (fallback: GitHub release)

Перед первым run:
  1. Зарегистрируйтесь на https://fxtun.ru или https://fxtun.dev
  2. Скопируйте API-токен из личного кабинета
  3. export FXTUN_TOKEN=sk_...

После запуска укажите в ${DOCKER_SERVICE}/.env:
  MAX_WEBHOOK_URL=https://<субдомен>.${FXTUN_PUBLIC_DOMAIN}/api/webhooks/max
  # для service-c также:
  MAX_MINI_APP_URL=https://<субдомен>.${FXTUN_PUBLIC_DOMAIN}/max-app

Затем:
  docker compose exec -T ${DOCKER_SERVICE} php artisan max:webhook:subscribe
EOF
}

print_alternatives() {
    cat >&2 <<'EOF'

fxTunnel сейчас недоступен из WSL (сайт или порт 4443 не отвечает в отведённый таймаут).
Если https://fxtun.dev открывается в браузере Windows — это типично: WSL резолвит DNS медленнее.

Попробуйте:
  FXTUN_CONNECT_TIMEOUT=45 ./scripts/fxtun-tunnel.sh run
  FXTUN_SERVER=95.181.173.114 FXTUN_PUBLIC_DOMAIN=fxtun.dev ./scripts/fxtun-tunnel.sh service-c run
  FXTUN_SKIP_PREFLIGHT=1 FXTUN_SERVER=95.181.173.114 ./scripts/fxtun-tunnel.sh service-c run

Или ускорить DNS в WSL (/etc/wsl.conf → generateResolvConf=false, nameserver 8.8.8.8 в /etc/resolv.conf).

Рабочие варианты для MAX webhook на порту ${PORT}:

  1) VPN — затем любой туннель:
     export FXTUN_TOKEN=sk_...
     ./scripts/fxtun-tunnel.sh run

  2) Свой VPS + nginx + Let's Encrypt (стабильный постоянный URL):
     прокси https://your-vps.example.com → localhost:8082 через SSH -R или frp

  3) Self-hosted fxTunnel на VPS (если есть сервер):
     https://github.com/mephistofox/fxtun.dev

  4) Microsoft Dev Tunnel (если aka.ms открывается):
     curl -sL https://aka.ms/DevTunnelCliInstall | bash
     devtunnel user login
     devtunnel host -p 8082 --allow-anonymous

После получения HTTPS URL:
  MAX_WEBHOOK_URL=https://.../api/webhooks/max
  docker compose exec -T ${DOCKER_SERVICE} php artisan max:webhook:subscribe
EOF
}

check_endpoint() {
    local label="$1"
    local url="$2"
    local code

    code="$(curl -s -o /dev/null -w '%{http_code}' \
        --connect-timeout "${FXTUN_CONNECT_TIMEOUT}" \
        --max-time "$((FXTUN_CONNECT_TIMEOUT + 10))" \
        "${url}" 2>/dev/null || true)"
    if [[ ! "${code}" =~ ^[0-9]{3}$ ]] || [[ "${code}" == "000" ]]; then
        echo "  ${label}: недоступен"
        return 1
    fi

    echo "  ${label}: доступен (HTTP ${code})"
    return 0
}

check_tcp_port() {
    local host="$1"
    local port="$2"

    if command -v nc >/dev/null 2>&1; then
        timeout "${FXTUN_CONNECT_TIMEOUT}" nc -z "${host}" "${port}" >/dev/null 2>&1
        return $?
    fi

    timeout "${FXTUN_CONNECT_TIMEOUT}" bash -c "echo >/dev/tcp/${host}/${port}" 2>/dev/null
}

dns_resolves() {
    local host="$1"
    local timeout_sec="${2:-5}"

    if getent ahostsv4 "${host}" >/dev/null 2>&1; then
        return 0
    fi

    if command -v dig >/dev/null 2>&1; then
        timeout "${timeout_sec}" dig +time="${timeout_sec}" +tries=1 +short "${host}" A >/dev/null 2>&1
        return $?
    fi

    timeout "${timeout_sec}" getent hosts "${host}" >/dev/null 2>&1
}

maybe_use_fxtun_ip() {
    local reason="$1"

    if [[ "${FXTUN_SERVER}" != "fxtun.dev" && "${FXTUN_SERVER}" != "fxtun.ru" ]]; then
        return 1
    fi

    if ! check_tcp_port "${FXTUN_SERVER_IP}" 4443; then
        return 1
    fi

    echo "  ${reason} — CLI подключится к ${FXTUN_SERVER_IP} (публичный URL: *.${FXTUN_PUBLIC_DOMAIN})"
    FXTUN_SERVER="${FXTUN_SERVER_IP}"
    FXTUN_PUBLIC_DOMAIN="fxtun.dev"
    return 0
}

preflight_fxtun() {
    if [[ "${FXTUN_SKIP_PREFLIGHT:-0}" == "1" ]]; then
        echo "Пропуск preflight (FXTUN_SKIP_PREFLIGHT=1), сервер: ${FXTUN_SERVER}"
        return 0
    fi

    local site_ok=0
    local port_ok=0

    echo "Проверка доступности fxTunnel (таймаут ${FXTUN_CONNECT_TIMEOUT}s, сервер: ${FXTUN_SERVER}) ..."

    if check_endpoint "https://${FXTUN_SERVER}/" "https://${FXTUN_SERVER}/"; then
        site_ok=1
    fi

    if [[ "${site_ok}" -eq 0 && "${FXTUN_SERVER}" != "fxtun.dev" ]]; then
        if check_endpoint "https://fxtun.dev/" "https://fxtun.dev/"; then
            echo "  fxtun.dev доступен — переключаюсь с ${FXTUN_SERVER} на fxtun.dev"
            FXTUN_SERVER="fxtun.dev"
            FXTUN_PUBLIC_DOMAIN="fxtun.dev"
            site_ok=1
        fi
    fi

    if check_tcp_port "${FXTUN_SERVER}" 4443; then
        echo "  tcp://${FXTUN_SERVER}:4443: доступен"
        port_ok=1
    else
        echo "  tcp://${FXTUN_SERVER}:4443: недоступен"
        if maybe_use_fxtun_ip "tcp://${FXTUN_SERVER}:4443 недоступен, обход DNS WSL"; then
            port_ok=1
        fi
    fi

    if [[ "${site_ok}" -eq 0 && "${port_ok}" -eq 0 ]]; then
        if maybe_use_fxtun_ip "DNS WSL нестабилен"; then
            return 0
        fi

        echo
        print_alternatives
        return 1
    fi

    # HTTPS или DNS часто падают в WSL, а nc к hostname успевает — Go-клиент fxtun снова резолвит и падает.
    if [[ "${site_ok}" -eq 0 && "${port_ok}" -eq 1 ]]; then
        maybe_use_fxtun_ip "HTTPS/DNS нестабильны в WSL" || true
    elif [[ "${port_ok}" -eq 1 && "${FXTUN_SERVER}" =~ ^(fxtun\.dev|fxtun\.ru)$ ]]; then
        if ! dns_resolves "${FXTUN_SERVER}" 5; then
            maybe_use_fxtun_ip "DNS lookup ${FXTUN_SERVER} не ответил за 5s" || true
        fi
    fi

    return 0
}

ensure_fxtun() {
    if command -v "${FXTUN_BIN}" >/dev/null 2>&1; then
        FXTUN_BIN="$(command -v "${FXTUN_BIN}")"
        return 0
    fi

    if [[ -x "${BIN_DIR}/fxtunnel" ]]; then
        FXTUN_BIN="${BIN_DIR}/fxtunnel"
        return 0
    fi

    if [[ -x "${BIN_DIR}/fxtun" ]]; then
        FXTUN_BIN="${BIN_DIR}/fxtun"
        return 0
    fi

    echo "fxtun не найден. Запустите: $(basename "$0") install" >&2
    exit 1
}

install_from_github() {
    local tmp_bin="${BIN_DIR}/fxtunnel.new"

    echo "Скачиваю fxtunnel с GitHub ..."
    mkdir -p "${BIN_DIR}"
    curl -fsSL --connect-timeout 30 --retry 2 --retry-delay 3 \
        -o "${tmp_bin}" "${GITHUB_RELEASE}"
    chmod +x "${tmp_bin}"
    mv "${tmp_bin}" "${BIN_DIR}/fxtunnel"
    ln -sf "${BIN_DIR}/fxtunnel" "${BIN_DIR}/fxtun"
    echo "Установлено: ${BIN_DIR}/fxtunnel"
}

install_fxtun() {
    if curl -fsSL --connect-timeout 15 --max-time 60 "${INSTALL_URL}" | sh; then
        ensure_fxtun
    else
        echo "install.sh с ${INSTALL_URL} недоступен, пробую GitHub ..." >&2
        install_from_github
        ensure_fxtun
    fi

    "${FXTUN_BIN}" version 2>/dev/null || true
    echo
    echo "Дальше: export FXTUN_TOKEN=sk_...  (токен с https://fxtun.ru или https://fxtun.dev)"
}

ensure_auth() {
    if [[ -z "${FXTUN_TOKEN:-}" ]]; then
        echo "Нужен API-токен fxTunnel." >&2
        echo "  export FXTUN_TOKEN=sk_...   # из https://fxtun.ru или https://fxtun.dev" >&2
        echo "  $(basename "$0") run" >&2
        exit 1
    fi
}

check_local_service() {
    local code
    code="$(curl -s -o /dev/null -w '%{http_code}' --connect-timeout 3 "${LOCAL_URL}" || true)"
    if [[ "${code}" != "200" && "${code}" != "301" && "${code}" != "302" && "${code}" != "404" ]]; then
        echo "На ${LOCAL_URL} нет ответа (HTTP ${code:-нет связи})." >&2
        echo "  cd ${ROOT_DIR} && docker compose up -d ${DOCKER_SERVICE}" >&2
        exit 1
    fi
    echo "Локальный сервер доступен: ${LOCAL_URL} (HTTP ${code})"
}

run_tunnel() {
    ensure_fxtun
    if ! preflight_fxtun; then
        exit 1
    fi
    ensure_auth
    check_local_service

    local -a cmd=("${FXTUN_BIN}" -s "${FXTUN_SERVER}" -t "${FXTUN_TOKEN}" http "${PORT}")
    if [[ -n "${FXTUN_DOMAIN:-}" ]]; then
        cmd+=(--domain "${FXTUN_DOMAIN}")
    fi

    echo
    echo "Запуск fxTunnel → ${TUNNEL_SERVICE} localhost:${PORT} (сервер: ${FXTUN_SERVER})"
    echo "Webhook MAX: MAX_WEBHOOK_URL=https://<субдомен>.${FXTUN_PUBLIC_DOMAIN}/api/webhooks/max"
    if [[ "${TUNNEL_SERVICE}" == "service-c" ]]; then
        echo "Mini-app MAX: MAX_MINI_APP_URL=https://<субдомен>.${FXTUN_PUBLIC_DOMAIN}/max-app"
        echo "Кабинет MAX: URL mini-app → MAX_MINI_APP_URL; webhook → MAX_WEBHOOK_URL"
    fi
    echo "Остановка: Ctrl+C"
    echo
    echo "⚠ Mini-app в MAX/Safari: fxTunnel может показывать «Dev Tunnel Warning» вместо приложения."
    echo "  Webhook (POST) обходит warning; GET из WebView — нет. Для телефона надёжнее VPS + HTTPS."
    echo
    exec "${cmd[@]}"
}

check_all() {
    echo "Локальный ${TUNNEL_SERVICE}:"
    check_local_service || true
    echo
    preflight_fxtun || true
}

main() {
    local action="${1:-run}"

    if [[ "${action}" == "service-b" || "${action}" == "service-c" ]]; then
        shift
        action="${1:-run}"
    fi

    case "${action}" in
        install)
            install_fxtun
            ;;
        run)
            run_tunnel
            ;;
        check)
            check_all
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
