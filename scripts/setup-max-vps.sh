#!/usr/bin/env bash
# Полный цикл подготовки MAX mini-app через VPS hybrid (SSH reverse tunnel).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

TUNNEL_ENV="${ROOT}/scripts/vps-tunnel.env"
if [[ -f "${TUNNEL_ENV}" ]]; then
    # shellcheck disable=SC1090
    set -a
    source "${TUNNEL_ENV}"
    set +a
fi

VPS_DOMAIN="${VPS_DOMAIN:-}"
if [[ -z "${VPS_DOMAIN}" ]]; then
    echo "Задайте VPS_DOMAIN в scripts/vps-tunnel.env или export VPS_DOMAIN=..." >&2
    echo "  cp scripts/vps-tunnel.env.example scripts/vps-tunnel.env" >&2
    exit 1
fi

VPS_BASE="https://${VPS_DOMAIN}"
ENV_FILE="service-c/.env"

echo "=== MAX + VPS hybrid (${VPS_BASE}) ==="
echo ""

echo "=== 1. service-c ==="
docker compose up -d service-c
docker compose exec -T service-c sh -c 'pkill -f "vite" 2>/dev/null || true; rm -f public/hot'

echo ""
echo "=== 2. Production-сборка фронтенда ==="
"${ROOT}/scripts/build-max-app.sh"

echo ""
echo "=== 3. Профиль бота ==="
docker compose exec -T service-c php artisan max:bot:info || true

echo ""
echo "=== 4. Проверка .env ==="
if [[ ! -f "${ENV_FILE}" ]]; then
    echo "Нет ${ENV_FILE}. Скопируйте: cp service-c/.env.example service-c/.env" >&2
    exit 1
fi

required=(
    "MAX_BOT_ACCESS_TOKEN"
    "MAX_WEBHOOK_SECRET"
    "MAX_BOT_USERNAME"
)
for key in "${required[@]}"; do
    val="$(grep -E "^${key}=" "${ENV_FILE}" 2>/dev/null | cut -d= -f2- | tr -d '"' || true)"
    if [[ -z "${val}" ]]; then
        echo "  [!] ${key} не задан в ${ENV_FILE}"
    else
        echo "  [ok] ${key}"
    fi
done

for key in APP_URL MAX_WEBHOOK_URL; do
    val="$(grep -E "^${key}=" "${ENV_FILE}" 2>/dev/null | cut -d= -f2- | tr -d '"' || true)"
    expected=""
    case "${key}" in
        APP_URL) expected="${VPS_BASE}" ;;
        MAX_WEBHOOK_URL) expected="${VPS_BASE}/api/webhooks/max" ;;
    esac
    if [[ "${val}" != "${expected}" ]]; then
        echo "  [!] ${key}=${val:-<пусто>} — ожидается: ${expected}"
        echo "      ./scripts/vps-tunnel.sh print-env"
    else
        echo "  [ok] ${key}"
    fi
done

mini_expected="${VPS_BASE}/max-app"
mini_url="$(grep -E '^MAX_MINI_APP_URL=' "${ENV_FILE}" 2>/dev/null | cut -d= -f2- | tr -d '"' || true)"
if [[ -n "${mini_url}" && "${mini_url}" != "${mini_expected}" ]]; then
    echo "  [!] MAX_MINI_APP_URL=${mini_url} — ожидается: ${mini_expected} (или пусто)"
elif [[ -n "${mini_url}" ]]; then
    echo "  [ok] MAX_MINI_APP_URL=${mini_url}"
else
    echo "  [ok] MAX_MINI_APP_URL пуст — выводится из MAX_WEBHOOK_URL → ${mini_expected}"
fi

echo ""
echo "=== 5. SSH и nginx на VPS ==="
"${ROOT}/scripts/vps-tunnel.sh" check || true
echo "  Первый раз: ./scripts/vps-tunnel.sh apply-nginx-remote"
echo "  На VPS: sudo certbot --nginx -d ${VPS_DOMAIN}"

echo ""
echo "=== 6. Локальная проверка mini-app ==="
docker compose exec -T service-c php artisan max:miniapp:verify || true

echo ""
echo "=== 7. Туннель (отдельный терминал) ==="
echo "  ./scripts/vps-tunnel-watch.sh"
echo "  ./scripts/diag-max-vps.sh"
echo ""
echo "=== 8. После запуска туннеля ==="
echo "  Кабинет MAX → URL мини-приложения: ${mini_expected}"
echo "  docker compose exec -T service-c php artisan config:clear"
echo "  docker compose exec -T service-c php artisan max:webhook:subscribe"
echo "  docker compose exec -T service-c php artisan max:ui-stand:send"
echo ""
echo "Готово."
