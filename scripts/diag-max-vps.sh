#!/usr/bin/env bash
# Диагностика MAX mini-app: локальный service-c + VPS hybrid tunnel.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

TUNNEL_ENV="${ROOT}/scripts/vps-tunnel.env"
if [[ -f "${TUNNEL_ENV}" ]]; then
    # shellcheck disable=SC1090
    set -a
    source "${TUNNEL_ENV}"
    set +a
fi

PORT="${SERVICE_C_PORT:-8083}"

echo "=== MAX VPS hybrid diagnostic (service-c :${PORT}) ==="
echo ""

echo "=== 1. service-c container ==="
docker compose ps service-c 2>&1 || true

echo ""
echo "=== 2. Vite hot vs production build ==="
docker compose exec -T service-c sh -c '
  if [ -f public/hot ]; then echo "[!!] public/hot EXISTS — MAX получит localhost:5174"; cat public/hot; else echo "[OK] no public/hot"; fi
  if [ -f public/max-build/manifest.json ]; then echo "[OK] public/max-build/manifest.json"; else echo "[!!] NO build — ./scripts/build-max-app.sh"; fi
' 2>/dev/null || echo "[!!] service-c container not running"

echo ""
echo "=== 3. scripts/vps-tunnel.env ==="
if [[ -f scripts/vps-tunnel.env ]]; then
    grep -E '^(VPS_HOST|VPS_USER|VPS_DOMAIN|REMOTE_BIND_PORT|SERVICE_C_PORT)=' scripts/vps-tunnel.env || true
else
    echo "[--] scripts/vps-tunnel.env отсутствует — cp scripts/vps-tunnel.env.example scripts/vps-tunnel.env"
fi

echo ""
echo "=== 4. service-c/.env URLs ==="
grep -E '^(APP_URL|MAX_WEBHOOK_URL|MAX_MINI_APP_URL|MAX_PUBLIC_APP_URL)=' service-c/.env 2>/dev/null \
    || echo "no service-c/.env"

echo ""
echo "=== 5. Local /max-app ==="
curl -sS --connect-timeout 5 "http://127.0.0.1:${PORT}/max-app" 2>/dev/null | tr '>' '\n' \
    | grep -E '5174|max-build/assets|не собран|Tunnel Warning|id="max-app"' | head -6 \
    || echo "service-c not reachable on :${PORT}"

echo ""
echo "=== 6. VPS tunnel check ==="
if [[ -n "${VPS_HOST:-}" && -n "${VPS_DOMAIN:-}" ]]; then
    "${ROOT}/scripts/vps-tunnel.sh" verify 2>&1 || true
else
    echo "[--] VPS_DOMAIN не задан — пропуск публичной проверки"
    "${ROOT}/scripts/max-tunnel-check.sh" 2>&1 || true
fi

echo ""
echo "=== 7. artisan max:miniapp:verify ==="
docker compose exec -T service-c php artisan max:miniapp:verify 2>&1 || true

echo ""
echo "=== Рекомендации ==="
echo "  Подготовка:  ./scripts/setup-max-vps.sh"
echo "  Туннель:     ./scripts/vps-tunnel-watch.sh"
echo "  После UI:    ./scripts/build-max-app.sh"
echo "  Логи MAX:    docker compose exec -T service-c tail -f storage/logs/messMax.log"
