#!/usr/bin/env sh
# E2E-проверка WebSocket-счётчиков (сценарий «2 вкладки», export + mail).
# Запуск из корня репозитория: sh scripts/e2e-verify-report-stats.sh

set -eu

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

log() {
    printf '[e2e] %s\n' "$1"
}

fail() {
    printf '[e2e] FAIL: %s\n' "$1" >&2
    exit 1
}

log "Generating Passport token..."
TOKEN="$(docker compose exec -T main-app php artisan tinker --execute='echo App\Models\User::first()->createTokenForSession();' | tr -d '\r' | tail -n 1)"
[ -n "$TOKEN" ] || fail "Could not create passport token"

log "Checking broadcasting auth..."
AUTH_CODE="$(docker compose exec -T main-app sh -c "wget -qO- --server-response --post-data='socket_id=123.456&channel_name=private-report-jobs.stats' --header='Accept: application/json' --header='Authorization: Bearer ${TOKEN}' --header='Content-Type: application/x-www-form-urlencoded' http://localhost:8000/broadcasting/auth 2>&1 | awk '/HTTP\\//{code=\$2} END{print code}')"
[ "$AUTH_CODE" = "200" ] || fail "POST /broadcasting/auth returned HTTP ${AUTH_CODE}"

log "Running WebSocket E2E inside main-app container (2 simulated tabs)..."
docker compose cp scripts/e2e-verify-report-stats.cjs main-app:/var/www/html/e2e-verify-report-stats.cjs

docker compose exec -T \
    -e TOKEN="$TOKEN" \
    -e REVERB_APP_KEY="${REVERB_APP_KEY:-local-app-key}" \
    -e REVERB_HOST="${REVERB_HOST:-localhost}" \
    -e REVERB_PORT="${REVERB_PORT:-8090}" \
    -e AUTH_BASE_URL="${AUTH_BASE_URL:-http://localhost}" \
    -e MAIN_APP_URL="${MAIN_APP_URL:-http://localhost}" \
    -e SERVICE_B_URL="${SERVICE_B_URL:-http://localhost:8082}" \
    -e E2E_TIMEOUT_MS=120000 \
    main-app sh -c 'cd /var/www/html && node e2e-verify-report-stats.cjs'

log "E2E verification finished successfully"
