#!/usr/bin/env bash
# Запуск туннеля exampleprojectsail.fxtun.dev + проверка prerequisites.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "=== Проверка перед запуском туннеля ==="

if [[ -z "${FXTUN_TOKEN:-}" ]]; then
    echo ""
    echo "Нужен FXTUN_TOKEN (sk_... из https://fxtun.dev → личный кабинет):"
    echo "  export FXTUN_TOKEN=sk_..."
    echo "  ./scripts/start-max-tunnel.sh"
    exit 1
fi

if ! docker compose ps service-c 2>/dev/null | grep -qE 'Up|running'; then
    echo "Запускаю service-c ..."
    docker compose up -d service-c
fi

code="$(curl -sS -o /dev/null -w '%{http_code}' --connect-timeout 5 http://127.0.0.1:8083/max-app 2>/dev/null || echo 000)"
if [[ "${code}" != "200" ]]; then
    echo "[!] service-c не отвечает на :8083 (HTTP ${code})" >&2
    exit 1
fi
echo "[ok] service-c → HTTP ${code}"

if ! docker compose exec -T service-c test -f public/build/manifest.json 2>/dev/null; then
    echo "Сборка max-app (нет manifest.json) ..."
    "${ROOT}/scripts/build-max-app.sh"
fi

chmod +x "${ROOT}/scripts/fxtun-exampleprojectsail.sh" "${ROOT}/scripts/fxtun-exampleprojectsail-watch.sh" 2>/dev/null || true

if ! command -v fxtun >/dev/null 2>&1 && ! command -v fxtunnel >/dev/null 2>&1 \
    && [[ ! -x "${HOME}/.local/bin/fxtunnel" ]]; then
    echo "Устанавливаю fxtun CLI ..."
    "${ROOT}/scripts/fxtun-tunnel.sh" install
fi

echo ""
echo "=== Запуск туннеля (Ctrl+C для остановки) ==="
echo "Публичный URL: https://exampleprojectsail.fxtun.dev"
echo "В другом терминале: ./scripts/diag-max-fxtun.sh"
echo ""

exec "${ROOT}/scripts/fxtun-exampleprojectsail-watch.sh"
