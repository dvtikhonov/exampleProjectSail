#!/usr/bin/env bash
# Quick Tunnel для service-b (MAX webhook). Альтернатива ngrok через Cloudflare.
# Документация: https://developers.cloudflare.com/cloudflare-one/networks/connectors/cloudflare-tunnel/do-more-with-tunnels/trycloudflare/

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
CLOUDFLARED="${CLOUDFLARED_BIN:-${BIN_DIR}/cloudflared}"
CLOUDFLARED_VERSION="${CLOUDFLARED_VERSION:-2025.4.2}"
# http2 — TCP вместо QUIC/UDP; стабильнее в WSL2, РФ и за корпоративным firewall.
CLOUDFLARED_PROTOCOL="${CLOUDFLARED_PROTOCOL:-http2}"
DEB_URL="https://pkg.cloudflare.com/cloudflared/pool/main/c/cloudflared/cloudflared_${CLOUDFLARED_VERSION}_amd64.deb"
GITHUB_URL="https://github.com/cloudflare/cloudflared/releases/download/${CLOUDFLARED_VERSION}/cloudflared-linux-amd64"

usage() {
    cat <<EOF
Использование: $(basename "$0") [service-b|service-c] [install|run]

  install  — установить cloudflared в ~/.local/bin (если ещё нет)
  run      — проверить сервис и запустить Quick Tunnel (по умолчанию service-b:8082)
  check    — проверить локальный сервис и доступность api.trycloudflare.com

  service-c — туннель на service-c (порт 8083) для MAX webhook / mini-app

Переменные:
  TUNNEL_SERVICE          service-b (по умолчанию) или service-c
  SERVICE_B_PORT          порт service-b (по умолчанию: 8082)
  SERVICE_C_PORT          порт service-c (по умолчанию: 8083)
  CLOUDFLARED_BIN         путь к бинарнику cloudflared
  CLOUDFLARED_VERSION     версия для загрузки (по умолчанию: 2025.4.2)
  CLOUDFLARED_PROTOCOL    quic | http2 | auto (по умолчанию: http2)
  CLOUDFLARED_USE_DOCKER  1 — запуск через Docker-образ cloudflare/cloudflared

После запуска скопируйте HTTPS URL из вывода и укажите в ${DOCKER_SERVICE}/.env:
  MAX_WEBHOOK_URL=https://<id>.trycloudflare.com/api/webhooks/max

Для service-c дополнительно:
  MAX_MINI_APP_URL=https://<id>.trycloudflare.com/max-app

Затем:
  docker compose exec -T ${DOCKER_SERVICE} php artisan max:webhook:subscribe
EOF
}

download_file() {
    local url="$1"
    local dest="$2"
    curl -fsSL --connect-timeout 30 --retry 2 --retry-delay 3 -o "${dest}" "${url}"
}

install_from_deb() {
    local tmp_dir deb_path extract_dir binary_path
    tmp_dir="$(mktemp -d)"
    deb_path="${tmp_dir}/cloudflared.deb"
    extract_dir="${tmp_dir}/extract"

    echo "Скачиваю .deb с pkg.cloudflare.com (${CLOUDFLARED_VERSION}) ..."
    download_file "${DEB_URL}" "${deb_path}"

    mkdir -p "${extract_dir}"
    dpkg-deb -x "${deb_path}" "${extract_dir}"

    binary_path="$(find "${extract_dir}" -type f -name cloudflared | head -n1)"
    if [[ -z "${binary_path}" || ! -x "${binary_path}" ]]; then
        echo "Не удалось извлечь cloudflared из .deb" >&2
        rm -rf "${tmp_dir}"
        return 1
    fi

    mkdir -p "${BIN_DIR}"
    cp "${binary_path}" "${CLOUDFLARED}"
    chmod +x "${CLOUDFLARED}"
    rm -rf "${tmp_dir}"
    echo "Установлено из .deb: ${CLOUDFLARED}"
}

install_from_github() {
    echo "Скачиваю бинарник с GitHub (${CLOUDFLARED_VERSION}) ..."
    mkdir -p "${BIN_DIR}"
    download_file "${GITHUB_URL}" "${CLOUDFLARED}"
    chmod +x "${CLOUDFLARED}"
    echo "Установлено с GitHub: ${CLOUDFLARED}"
}

install_from_docker() {
    if ! command -v docker >/dev/null 2>&1; then
        return 1
    fi

    echo "Пробую извлечь cloudflared из Docker-образа ..."
    docker pull cloudflare/cloudflared:latest
    local cid
    cid="$(docker create cloudflare/cloudflared:latest)"
    mkdir -p "${BIN_DIR}"
    docker cp "${cid}:/usr/local/bin/cloudflared" "${CLOUDFLARED}" 2>/dev/null \
        || docker cp "${cid}:/usr/bin/cloudflared" "${CLOUDFLARED}"
    docker rm "${cid}" >/dev/null
    chmod +x "${CLOUDFLARED}"
    echo "Установлено из Docker: ${CLOUDFLARED}"
}

print_manual_help() {
    cat >&2 <<EOF
Не удалось установить cloudflared автоматически.

Вариант A — .deb с Cloudflare (обычно работает, когда GitHub недоступен):
  curl -L -o /tmp/cloudflared.deb ${DEB_URL}
  mkdir -p /tmp/cf && dpkg-deb -x /tmp/cloudflared.deb /tmp/cf
  cp /tmp/cf/usr/bin/cloudflared ${CLOUDFLARED}
  chmod +x ${CLOUDFLARED}

Вариант B — apt (нужен sudo):
  curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg | sudo tee /usr/share/keyrings/cloudflare-main.gpg >/dev/null
  echo 'deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared any main' | sudo tee /etc/apt/sources.list.d/cloudflared.list
  sudo apt-get update && sudo apt-get install -y cloudflared

Вариант C — Docker без установки бинарника:
  CLOUDFLARED_USE_DOCKER=1 ./scripts/cloudflared-tunnel.sh
EOF
}

ensure_cloudflared() {
    if command -v cloudflared >/dev/null 2>&1; then
        CLOUDFLARED="$(command -v cloudflared)"
        return 0
    fi

    if [[ -x "${CLOUDFLARED}" ]]; then
        return 0
    fi

    if install_from_deb; then
        return 0
    fi

    if install_from_github; then
        return 0
    fi

    if install_from_docker; then
        return 0
    fi

    print_manual_help
    exit 1
}

print_tunnel_alternatives() {
    cat >&2 <<'EOF'

Quick Tunnel Cloudflare недоступен (api.trycloudflare.com не отвечает).
Это типично для РФ: ngrok, VK Tunnel и trycloudflare часто блокируются или таймаутят.

Рабочие альтернативы для MAX webhook на порту ${PORT}:

  1) fxTunnel (рекомендуется для РФ):
     curl -fsSL https://fxtun.ru/install.sh | sh
     export FXTUN_TOKEN=sk_...   # токен с https://fxtun.ru
     ./scripts/fxtun-tunnel.sh ${TUNNEL_SERVICE}

  2) cloudflared через VPN (если есть):
     ./scripts/cloudflared-tunnel.sh ${TUNNEL_SERVICE}

  3) Microsoft Dev Tunnel (GitHub/Microsoft аккаунт):
     curl -sL https://aka.ms/DevTunnelCliInstall | bash
     devtunnel user login
     devtunnel host -p ${PORT} --allow-anonymous

  4) Свой VPS + frp — стабильный постоянный HTTPS URL.

После получения HTTPS URL:
  MAX_WEBHOOK_URL=https://.../api/webhooks/max
  docker compose exec -T ${DOCKER_SERVICE} php artisan max:webhook:subscribe
EOF
}

preflight_quick_tunnel() {
    local code
    code="$(curl -s -o /dev/null -w '%{http_code}' --connect-timeout 8 --max-time 12 \
        -X POST https://api.trycloudflare.com/tunnel 2>/dev/null || echo 000)"
    if [[ "${code}" == "000" ]]; then
        echo "Предупреждение: api.trycloudflare.com недоступен (HTTP ${code})." >&2
        print_tunnel_alternatives
        exit 1
    fi
}

check_local_service() {
    local code
    code="$(curl -s -o /dev/null -w '%{http_code}' --connect-timeout 3 "${LOCAL_URL}" || true)"
    if [[ "${code}" != "200" && "${code}" != "301" && "${code}" != "302" && "${code}" != "404" ]]; then
        echo "На ${LOCAL_URL} нет ответа (HTTP ${code:-нет связи})." >&2
        echo "Запустите ${DOCKER_SERVICE} из корня репозитория:" >&2
        echo "  cd ${ROOT_DIR} && docker compose up -d ${DOCKER_SERVICE}" >&2
        exit 1
    fi
    echo "Локальный сервер доступен: ${LOCAL_URL} (HTTP ${code})"
}

print_tunnel_1033_help() {
    cat >&2 <<'EOF'

⚠ Cloudflare Error 1033: туннель зарегистрирован, но запросы снаружи не доходят до cloudflared.
  Локальный ${TUNNEL_SERVICE} (${LOCAL_URL}) в порядке — проблема в маршрутизации trycloudflare.com.

  Что попробовать:
    1) ./scripts/fxtun-tunnel.sh ${TUNNEL_SERVICE}   — рекомендуется для РФ
    2) cloudflared через VPN
    3) CLOUDFLARED_USE_DOCKER=1 ./scripts/cloudflared-tunnel.sh ${TUNNEL_SERVICE}

  После смены URL обновите ${DOCKER_SERVICE}/.env и выполните max:webhook:subscribe.
EOF
}

verify_tunnel_reachable() {
    local tunnel_url="$1"
    local attempts="${2:-20}"
    local attempt code

    echo
    echo "URL туннеля: ${tunnel_url}"
    echo "MAX_WEBHOOK_URL=${tunnel_url}/api/webhooks/max"
    if [[ "${TUNNEL_SERVICE}" == "service-c" ]]; then
        echo "MAX_MINI_APP_URL=${tunnel_url}/max-app"
    fi
    echo "Проверка доступности извне (до $((attempts * 3)) с)..."

    for ((attempt = 1; attempt <= attempts; attempt++)); do
        code="$(curl -s -o /dev/null -w '%{http_code}' --connect-timeout 5 --max-time 10 \
            "${tunnel_url}/" 2>/dev/null || echo 000)"

        if [[ "${code}" != "530" && "${code}" != "000" ]]; then
            echo "Туннель доступен извне (HTTP ${code}). Можно выполнять max:webhook:subscribe."
            return 0
        fi

        sleep 3
    done

    print_tunnel_1033_help
    return 1
}

run_cloudflared_with_verify() {
    local target_url="$1"
    local log_file
    log_file="$(mktemp)"

    "${CLOUDFLARED}" tunnel --protocol "${CLOUDFLARED_PROTOCOL}" --url "${target_url}" 2>&1 | tee "${log_file}" &
    local cf_pid=$!

    (
        local tunnel_url=""
        local waited=0

        while [[ ${waited} -lt 45 ]]; do
            tunnel_url="$(grep -oE 'https://[a-z0-9-]+\.trycloudflare\.com' "${log_file}" 2>/dev/null | head -n1 || true)"
            if [[ -n "${tunnel_url}" ]]; then
                break
            fi
            sleep 1
            waited=$((waited + 1))
        done

        if [[ -n "${tunnel_url}" ]]; then
            verify_tunnel_reachable "${tunnel_url}" || true
        else
            echo "Не удалось извлечь URL туннеля из вывода cloudflared." >&2
        fi
    ) &

    wait "${cf_pid}" || true
    rm -f "${log_file}"
}

run_tunnel_native() {
    ensure_cloudflared
    preflight_quick_tunnel
    check_local_service
    echo
    echo "Запуск Quick Tunnel → ${TUNNEL_SERVICE} ${LOCAL_URL} (протокол: ${CLOUDFLARED_PROTOCOL})"
    echo "Webhook MAX: MAX_WEBHOOK_URL=https://<ваш-id>.trycloudflare.com/api/webhooks/max"
    if [[ "${TUNNEL_SERVICE}" == "service-c" ]]; then
        echo "Mini-app MAX: MAX_MINI_APP_URL=https://<ваш-id>.trycloudflare.com/max-app"
    fi
    if [[ "${CLOUDFLARED_PROTOCOL}" == "http2" ]]; then
        echo "Подсказка: при ошибках QUIC/UDP используется HTTP/2 (TCP). Для QUIC: CLOUDFLARED_PROTOCOL=quic"
    fi
    echo "Остановка: Ctrl+C"
    echo
    run_cloudflared_with_verify "${LOCAL_URL}"
}

run_tunnel_docker() {
    check_local_service
    echo
    echo "Запуск через Docker: cloudflare/cloudflared → ${TUNNEL_SERVICE} host.docker.internal:${PORT} (протокол: ${CLOUDFLARED_PROTOCOL})"
    echo "Webhook MAX: MAX_WEBHOOK_URL=https://<ваш-id>.trycloudflare.com/api/webhooks/max"
    if [[ "${TUNNEL_SERVICE}" == "service-c" ]]; then
        echo "Mini-app MAX: MAX_MINI_APP_URL=https://<ваш-id>.trycloudflare.com/max-app"
    fi
    echo
    exec docker run --rm --add-host=host.docker.internal:host-gateway \
        -e "TUNNEL_TRANSPORT_PROTOCOL=${CLOUDFLARED_PROTOCOL}" \
        cloudflare/cloudflared:latest tunnel --protocol "${CLOUDFLARED_PROTOCOL}" \
        --url "http://host.docker.internal:${PORT}"
}

main() {
    local action="${1:-run}"

    if [[ "${action}" == "service-b" || "${action}" == "service-c" ]]; then
        shift
        action="${1:-run}"
    fi

    case "${action}" in
        install)
            ensure_cloudflared
            "${CLOUDFLARED}" --version
            ;;
        run)
            if [[ "${CLOUDFLARED_USE_DOCKER:-0}" == "1" ]]; then
                run_tunnel_docker
            else
                run_tunnel_native
            fi
            ;;
        check)
            check_local_service || true
            preflight_quick_tunnel || true
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
