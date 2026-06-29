#!/usr/bin/env bash
# Production-сборка max-app (обязательна для MAX web/desktop через туннель).
set -euo pipefail

cd "$(dirname "$0")/.."

echo "=== Сборка max-app в service-c ==="
docker compose up -d service-c

docker compose exec -T service-c sh -c 'pkill -f "vite" 2>/dev/null || true; rm -f public/hot'
docker compose exec -T service-c npm run build

if docker compose exec -T service-c test -f public/max-build/manifest.json; then
    echo "[ok] public/max-build/manifest.json"
else
    echo "[!!] manifest.json не создан" >&2
    exit 1
fi

echo ""
echo "Проверка локально:"
curl -sS http://127.0.0.1:8083/max-app | grep -oE 'id="max-app"|Фронтенд не собран|max-build/assets' | head -5 || true

echo ""
echo "Дальше: ./scripts/diag-max-vps.sh  (или ./scripts/diag-max-fxtun.sh для fxTunnel)"
