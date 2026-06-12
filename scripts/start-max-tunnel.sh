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

_host_provider="false"
[[ -f service-c/app/Providers/AppServiceProvider.php ]] && _host_provider="true"
_container_provider="false"
docker compose exec -T service-c test -f app/Providers/AppServiceProvider.php 2>/dev/null && _container_provider="true"

ensure_service_c_http_ok() {
    curl -sS -o /dev/null -w '%{http_code}' --connect-timeout 5 http://127.0.0.1:8083/max-app 2>/dev/null || echo 000
}

recover_service_c_mounts() {
    echo "Восстанавливаю service-c (пересоздание контейнера + очистка кэша) ..."
    docker compose up -d --force-recreate service-c
    sleep 3
    docker compose exec -T service-c php artisan config:clear >/dev/null 2>&1 || true
    docker compose exec -T service-c php artisan cache:clear >/dev/null 2>&1 || true
}

code="$(ensure_service_c_http_ok)"

if [[ "${code}" != "200" ]]; then
    if [[ "${_host_provider}" == "true" && "${_container_provider}" != "true" ]]; then
        echo "[!] bind mount app/ пустой в контейнере — пересоздаю service-c ..." >&2
        recover_service_c_mounts
        _container_provider="false"
        docker compose exec -T service-c test -f app/Providers/AppServiceProvider.php 2>/dev/null && _container_provider="true"
        code="$(ensure_service_c_http_ok)"
    else
        recover_service_c_mounts
        code="$(ensure_service_c_http_ok)"
    fi
fi

if [[ "${code}" != "200" ]]; then
    echo "[!] service-c не отвечает на :8083 (HTTP ${code})" >&2
    echo "    Диагностика: docker compose exec -T service-c ls -la app/Providers/" >&2
    echo "    Лог Laravel: tail -50 service-c/storage/logs/laravel.log" >&2
    exit 1
fi
echo "[ok] service-c → HTTP ${code}"

if ! docker compose exec -T service-c test -f public/build/manifest.json 2>/dev/null; then
    echo "Сборка max-app (нет manifest.json) ..."
    "${ROOT}/scripts/build-max-app.sh"
fi

chmod +x "${ROOT}/scripts/fxtun-exampleprojectsail.sh" "${ROOT}/scripts/fxtun-exampleprojectsail-watch.sh" 2>/dev/null || true
for _tunnel_script in \
    "${ROOT}/scripts/fxtun-exampleprojectsail-watch.sh" \
    "${ROOT}/scripts/fxtun-exampleprojectsail.sh" \
    "${ROOT}/scripts/fxtun-tunnel.sh"
do
    if [[ -f "${_tunnel_script}" ]] && grep -q $'\r' "${_tunnel_script}" 2>/dev/null; then
        sed -i 's/\r$//' "${_tunnel_script}"
    fi
done
unset _tunnel_script

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

exec bash "${ROOT}/scripts/fxtun-exampleprojectsail-watch.sh"
