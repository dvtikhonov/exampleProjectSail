#!/usr/bin/env bash
# Туннель service-c на зарезервированный субдомен exampleprojectsail.fxtun.dev
# Использование: export FXTUN_TOKEN=sk_... ; ./scripts/fxtun-exampleprojectsail.sh [run|check]
#
# После запуска в service-c/.env:
#   APP_URL=https://exampleprojectsail.fxtun.dev
#   MAX_WEBHOOK_URL=https://exampleprojectsail.fxtun.dev/api/webhooks/max
#   MAX_BOT_USERNAME=<из max:bot:info>   # кнопка open_app в сообщениях
# MAX_MINI_APP_URL можно не задавать — URL mini-app указывается в кабинете MAX.
# Кабинет MAX → URL мини-приложения: https://exampleprojectsail.fxtun.dev/max-app

set -euo pipefail

export FXTUN_DOMAIN="${FXTUN_DOMAIN:-exampleprojectsail}"
export FXTUN_PUBLIC_DOMAIN="${FXTUN_PUBLIC_DOMAIN:-fxtun.dev}"

# WSL: DNS к fxtun.dev часто таймаутит — CLI подключается по IP, URL остаётся *.fxtun.dev
if [[ -z "${FXTUN_SERVER:-}" ]]; then
    export FXTUN_SERVER="${FXTUN_SERVER_IP:-95.181.173.114}"
fi

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
exec bash "${ROOT_DIR}/scripts/fxtun-tunnel.sh" service-c "${1:-run}"
