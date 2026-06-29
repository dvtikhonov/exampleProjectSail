#!/usr/bin/env bash
# Диагностика синхронизации отзывов service-d (sync_status застрял в pending).
# Запуск на VPS из корня репозитория:
#   export COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml
#   ./scripts/diag-service-d-sync.sh
#
# Локально (без prod overlay):
#   ./scripts/diag-service-d-sync.sh

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT}"

export COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.yml}"

echo "=== service-d sync diagnostic (COMPOSE_FILE=${COMPOSE_FILE}) ==="
echo ""

echo "=== 1. Контейнеры service-d / service-d-queue / yandex-parser ==="
docker compose ps service-d service-d-queue yandex-parser 2>&1 || true

echo ""
echo "=== 2. QUEUE_CONNECTION в service-d/.env ==="
grep -E '^QUEUE_CONNECTION=' service-d/.env 2>/dev/null || echo "[!!] service-d/.env не найден"

echo ""
echo "=== 3. Последние логи service-d-queue (50 строк) ==="
docker compose logs --tail=50 service-d-queue 2>&1 || echo "[!!] service-d-queue недоступен"

echo ""
echo "=== 4. Очередь jobs / failed_jobs ==="
docker compose exec -T service-d php artisan tinker --execute="
echo 'jobs=' . DB::table('jobs')->count() . PHP_EOL;
echo 'failed_jobs=' . DB::table('failed_jobs')->count() . PHP_EOL;
\$org = App\Models\Organization::query()->latest('id')->first();
if (\$org) {
    echo 'latest_org_id=' . \$org->id . PHP_EOL;
    echo 'sync_status=' . \$org->sync_status->value . PHP_EOL;
    echo 'sync_error=' . (\$org->sync_error ?? 'null') . PHP_EOL;
} else {
    echo 'latest_org=none' . PHP_EOL;
}
" 2>&1 || echo "[!!] tinker недоступен (service-d не запущен?)"

echo ""
echo "=== 5. Проверка yandex-parser из service-d ==="
docker compose exec -T service-d curl -sS --connect-timeout 5 http://yandex-parser:3000/health 2>&1 \
    || docker compose exec -T service-d curl -sS --connect-timeout 5 http://yandex-parser:3000/ 2>&1 \
    || echo "[!!] yandex-parser не отвечает"

echo ""
echo "=== 6. Однократная обработка очереди (queue:work --once) ==="
docker compose exec -T service-d-queue php artisan queue:work --queue=default --once --timeout=900 2>&1 \
    || echo "[!!] queue:work --once не выполнен"

echo ""
echo "=== Рекомендации ==="
echo "  pending без перехода в syncing → воркер service-d-queue не обрабатывает jobs."
echo "  Запуск:  docker compose up -d service-d-queue yandex-parser"
echo "  Логи:    docker compose logs -f service-d-queue"
echo "  После деплоя: docker compose exec -T service-d-queue php artisan queue:restart"
echo "  Повтор sync: кнопка «Пересинхронизировать» в /settings или POST /api/organization/resync"
