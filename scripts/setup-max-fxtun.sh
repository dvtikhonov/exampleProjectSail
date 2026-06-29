#!/usr/bin/env bash
# Полный цикл подготовки MAX mini-app через fxTunnel (exampleprojectsail.fxtun.dev).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

FXTUN_SUBDOMAIN="${FXTUN_SUBDOMAIN:-exampleprojectsail}"
FXTUN_BASE="https://${FXTUN_SUBDOMAIN}.fxtun.dev"
ENV_FILE="service-c/.env"

echo "=== MAX + fxTunnel (${FXTUN_BASE}) ==="
echo ""

echo "=== 1. service-c ==="
docker compose up -d service-c
docker compose exec -T service-c sh -c 'pkill -f "vite" 2>/dev/null || true; rm -f public/hot'

echo ""
echo "=== 2. Production-сборка фронтенда ==="
docker compose exec -T service-c npm run build

echo ""
echo "=== 3. Профиль бота (MAX_BOT_USERNAME) ==="
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
    APP_URL) expected="${FXTUN_BASE}" ;;
    MAX_WEBHOOK_URL) expected="${FXTUN_BASE}/api/webhooks/max" ;;
  esac
  if [[ "${val}" != "${expected}" ]]; then
    echo "  [!] ${key}=${val:-<пусто>} — ожидается: ${expected}"
  else
    echo "  [ok] ${key}"
  fi
done

mini_url="$(grep -E '^MAX_MINI_APP_URL=' "${ENV_FILE}" 2>/dev/null | cut -d= -f2- | tr -d '"' || true)"
if [[ -n "${mini_url}" ]]; then
    echo "  [i] MAX_MINI_APP_URL=${mini_url} (для кнопки open_app приоритетнее MAX_BOT_USERNAME)"
else
    echo "  [ok] MAX_MINI_APP_URL пуст — кнопка open_app использует MAX_BOT_USERNAME"
fi

echo ""
echo "=== 5. Локальная проверка mini-app ==="
docker compose exec -T service-c php artisan max:miniapp:verify || true

echo ""
echo "=== 6. Туннель (отдельный терминал) ==="
if [[ -z "${FXTUN_TOKEN:-}" ]]; then
    echo "  export FXTUN_TOKEN=sk_...   # https://fxtun.dev → личный кабинет"
fi
echo "  ./scripts/fxtun-exampleprojectsail-watch.sh   # с автоперезапуском"
echo "  ./scripts/diag-max-fxtun.sh                   # диагностика"
echo ""
echo "=== 7. После запуска туннеля ==="
echo "  Кабинет MAX → URL мини-приложения: ${FXTUN_BASE}/max-app"
echo "  docker compose exec -T service-c php artisan max:webhook:subscribe"
echo "  docker compose exec -T service-c php artisan max:ui-stand:send"
echo ""
echo "Готово."
