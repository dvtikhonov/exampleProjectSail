#!/usr/bin/env bash
# Держит туннель exampleprojectsail.fxtun.dev с автоперезапуском (MAX web + desktop).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ -z "${FXTUN_TOKEN:-}" ]]; then
    echo "export FXTUN_TOKEN=sk_...   # https://fxtun.dev" >&2
    exit 1
fi

echo "Туннель exampleprojectsail.fxtun.dev (Ctrl+C для остановки)"
echo "Проверка: curl -H 'X-FxTunnel-Skip-Warning: 1' https://exampleprojectsail.fxtun.dev/max-app | head"
echo ""

while true; do
    echo "[$(date '+%H:%M:%S')] Запуск fxtun ..."
    if "${ROOT}/scripts/fxtun-exampleprojectsail.sh" run; then
        echo "[$(date '+%H:%M:%S')] Туннель завершился штатно."
    else
        echo "[$(date '+%H:%M:%S')] Туннель упал (код $?). Перезапуск через 5 с ..."
    fi
    sleep 5
done
