#!/usr/bin/env bash
# Диагностика MAX mini-app через fxTunnel (exampleprojectsail.fxtun.dev).
set -euo pipefail

cd "$(dirname "$0")/.."

SUB="${FXTUN_SUBDOMAIN:-exampleprojectsail}"
BASE="https://${SUB}.fxtun.dev"
MINI="${BASE}/max-app"
SKIP_HEADER="X-FxTunnel-Skip-Warning: 1"
VERIFY_OK=0

# curl из WSL часто не достаёт fxtun.dev (DNS); из контейнера service-c — надёжнее.
tunnel_curl() {
    docker compose exec -T service-c curl -sS \
        --connect-timeout 15 --max-time 45 \
        "$@"
}

echo "=== MAX + fxTunnel: ${SUB}.fxtun.dev ==="
echo ""

echo "=== 1. service-c локально ==="
code_local="$(curl -sS -o /dev/null -w '%{http_code}' --connect-timeout 5 http://127.0.0.1:8083/max-app 2>/dev/null || echo 000)"
echo "  http://127.0.0.1:8083/max-app → HTTP ${code_local}"
if [[ "${code_local}" != "200" ]]; then
    echo "  [!] Запустите: docker compose up -d service-c"
fi

echo ""
echo "=== 2. Vite build (manifest) ==="
if docker compose exec -T service-c test -f public/build/manifest.json 2>/dev/null; then
    echo "  [ok] public/build/manifest.json в контейнере"
else
    echo "  [!] Нет production-сборки → ./scripts/build-max-app.sh"
fi
if docker compose exec -T service-c test -f public/hot 2>/dev/null; then
    echo "  [!] public/hot есть — удалите: docker compose exec -T service-c rm -f public/hot"
fi

echo ""
echo "=== 3. fxtun процесс ==="
if ps aux | grep -E '[f]xtun|[f]xtunnel' | grep -v 'diag-max-fxtun'; then
    :
else
    echo "  [!] Туннель не запущен → ./scripts/start-max-tunnel.sh"
fi

echo ""
echo "=== 4. Туннель (из контейнера, skip header) ==="
body="$(tunnel_curl -H "${SKIP_HEADER}" "${MINI}" 2>/dev/null || true)"
if [[ -z "${body}" ]]; then
    echo "  [!] Нет ответа — туннель offline или сеть"
else
    if echo "${body}" | grep -q 'Dev Tunnel Warning'; then
        echo "  [!] fxTunnel warning вместо приложения"
    elif echo "${body}" | grep -q 'id="max-app"'; then
        echo "  [ok] HTML max-app через туннель"
        ct="$(tunnel_curl -D - -o /dev/null -H "${SKIP_HEADER}" "${MINI}" 2>/dev/null | grep -i '^content-type:' | head -1 || true)"
        echo "  ${ct:-Content-Type: ?}"
    elif echo "${body}" | grep -q 'Фронтенд не собран'; then
        echo "  [!] Заглушка «Фронтенд не собран» → ./scripts/build-max-app.sh"
    elif echo "${body}" | grep -qi 'Tunnel not found\|Tunnel unavailable'; then
        echo "  [!] fxTunnel: клиент не подключён"
    else
        echo "  [?] Неожиданный ответ ($(echo "${body}" | wc -c) байт)"
        echo "${body}" | head -3
    fi
fi

echo ""
echo "=== 5. Content-Type для MAX (как WebView, без skip) ==="
ct_browser="$(tunnel_curl -D - -o /dev/null \
    -H 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36' \
    -H 'Accept: text/html' \
    "${MINI}" 2>/dev/null | grep -i '^content-type:' | head -1 || true)"
echo "  ${ct_browser:-нет ответа}"
if echo "${ct_browser}" | grep -qi 'text/html'; then
    echo "  [ok] text/html — MAX web.max.ru отрендерит страницу"
elif echo "${ct_browser}" | grep -qi 'text/plain'; then
    echo "  [!] text/plain — MAX покажет сырой HTML; обновите service-c"
fi

echo ""
echo "=== 6. APP_URL в контейнере ==="
app_url="$(docker compose exec -T service-c php artisan config:show app.url --no-ansi 2>/dev/null | tail -1 | tr -d '\r ' || true)"
echo "  app.url=${app_url:-?}"
if [[ "${app_url}" == *"localhost"* ]] || [[ "${app_url}" == *"127.0.0.1"* ]]; then
    echo "  [!] Ожидается https://${SUB}.fxtun.dev — service-c/.env + config:clear"
fi

echo ""
echo "=== 7. artisan max:miniapp:verify (главная проверка) ==="
docker compose exec -T service-c php artisan config:clear >/dev/null 2>&1 || true
if docker compose exec -T service-c php artisan max:miniapp:verify 2>&1; then
    VERIFY_OK=1
fi

echo ""
if [[ "${VERIFY_OK}" -eq 1 ]]; then
    echo "=== ИТОГ: mini-app готов для MAX (web + desktop) ==="
    echo "  Откройте mini-app в MAX. Туннель должен оставаться запущенным."
    echo "  Webhook: docker compose exec -T service-c php artisan max:webhook:subscribe"
else
    echo "=== Рекомендации ==="
    echo "  Туннель:  ./scripts/start-max-tunnel.sh"
    echo "  Сборка:   ./scripts/build-max-app.sh"
    echo "  Webhook:  docker compose exec -T service-c php artisan max:webhook:subscribe"
fi
