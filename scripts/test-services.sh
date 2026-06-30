#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOCAL_ENV_FILE="${ROOT_DIR}/.env.testing.local"

if [[ -f "$LOCAL_ENV_FILE" ]]; then
    set -a
    # shellcheck source=/dev/null
    source "$LOCAL_ENV_FILE"
    set +a
fi

TEST_DATABASE="${TEST_DATABASE-}"
TEST_DB_USERNAME="${TEST_DB_USERNAME-}"
TEST_DB_PASSWORD="${TEST_DB_PASSWORD-}"
TEST_DB_HOST="${TEST_DB_HOST-}"
TEST_DB_PORT="${TEST_DB_PORT-}"

TEST_DATABASE="${TEST_DATABASE%$'\r'}"
TEST_DB_USERNAME="${TEST_DB_USERNAME%$'\r'}"
TEST_DB_PASSWORD="${TEST_DB_PASSWORD%$'\r'}"
TEST_DB_HOST="${TEST_DB_HOST%$'\r'}"
TEST_DB_PORT="${TEST_DB_PORT%$'\r'}"

TEST_DATABASE="${TEST_DATABASE:-sail_db_testing}"
SERVICE_D_TEST_DATABASE="${SERVICE_D_TEST_DATABASE:-service_d_db_testing}"
MYSQL_USER="${TEST_DB_USERNAME:-root}"
MYSQL_PASSWORD="${TEST_DB_PASSWORD:?Set TEST_DB_PASSWORD in .env.testing.local or environment}"
MYSQL_HOST="${TEST_DB_HOST:-host.docker.internal}"
MYSQL_PORT="${TEST_DB_PORT:-3306}"

usage() {
    cat <<'USAGE'
Usage:
  ./scripts/test-services.sh prepare
  ./scripts/test-services.sh all [--no-prepare]
  ./scripts/test-services.sh main-app [--no-prepare]
  ./scripts/test-services.sh service-a [--no-prepare]
  ./scripts/test-services.sh service-b [--no-prepare]
  ./scripts/test-services.sh service-c [--no-prepare]
  ./scripts/test-services.sh service-d [--no-prepare]
  ./scripts/test-services.sh service-e [--no-prepare]

Environment overrides:
  TEST_DATABASE=sail_db_testing
  SERVICE_D_TEST_DATABASE=service_d_db_testing
  TEST_DB_HOST=host.docker.internal
  TEST_DB_PORT=3306
  TEST_DB_USERNAME=root
  TEST_DB_PASSWORD=<required>

Local overrides can be stored in .env.testing.local.
USAGE
}

MODE="${1:-}"
SKIP_PREPARE=false

if [[ -z "$MODE" ]]; then
    usage
    exit 1
fi

if [[ "$MODE" == "-h" || "$MODE" == "--help" ]]; then
    usage
    exit 0
fi

shift

while [[ $# -gt 0 ]]; do
    case "$1" in
        --no-prepare)
            SKIP_PREPARE=true
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1" >&2
            usage
            exit 1
            ;;
    esac

    shift
done

compose_exec() {
    docker compose exec -T "$@"
}

artisan() {
    local service="$1"
    local database="$2"

    shift 2

    compose_exec \
        -e APP_ENV=testing \
        -e DB_CONNECTION=mysql \
        -e DB_HOST="$MYSQL_HOST" \
        -e DB_PORT="$MYSQL_PORT" \
        -e DB_DATABASE="$database" \
        -e DB_TEST_DATABASE="$database" \
        -e DB_USERNAME="$MYSQL_USER" \
        -e DB_PASSWORD="$MYSQL_PASSWORD" \
        "$service" php artisan "$@"
}

prepare_mysql_database() {
    local database="$1"

    compose_exec \
        -e TEST_DATABASE="$database" \
        -e TEST_DB_HOST="$MYSQL_HOST" \
        -e TEST_DB_PORT="$MYSQL_PORT" \
        -e TEST_DB_USERNAME="$MYSQL_USER" \
        -e TEST_DB_PASSWORD="$MYSQL_PASSWORD" \
        main-app php -r '$pdo = new PDO("mysql:host=" . getenv("TEST_DB_HOST") . ";port=" . getenv("TEST_DB_PORT"), getenv("TEST_DB_USERNAME"), getenv("TEST_DB_PASSWORD"), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); $database = str_replace("`", "``", getenv("TEST_DATABASE")); $pdo->exec("DROP DATABASE IF EXISTS `" . $database . "`"); $pdo->exec("CREATE DATABASE `" . $database . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");'
}

ensure_supported_mode() {
    case "$MODE" in
        prepare|all|main-app|service-a|service-b|service-c|service-d|service-e)
            ;;
        *)
            echo "Unknown mode: $MODE" >&2
            usage
            exit 1
            ;;
    esac
}

prepare_database() {
    echo "Preparing clean ${TEST_DATABASE} database..."

    prepare_mysql_database "$TEST_DATABASE"

    echo "Running main-app migrations..."
    artisan main-app "$TEST_DATABASE" migrate --database=mysql --path=database/migrations --env=testing --force

    echo "Running service-a migrations..."
    artisan service-a "$TEST_DATABASE" migrate --database=mysql --path=database/migrations --env=testing --force

    echo "Running service-b migrations..."
    artisan service-b "$TEST_DATABASE" migrate --database=mysql --path=database/migrations --env=testing --force

    echo "Running service-c migrations..."
    artisan service-c "$TEST_DATABASE" migrate --database=mysql --path=database/migrations --env=testing --force
}

prepare_service_d_database() {
    echo "Preparing clean ${SERVICE_D_TEST_DATABASE} database..."

    prepare_mysql_database "$SERVICE_D_TEST_DATABASE"

    echo "Running service-d migrations..."
    artisan service-d "$SERVICE_D_TEST_DATABASE" migrate --database=mysql --path=database/migrations --env=testing --force
}

prepare_all_databases() {
    prepare_database
    prepare_service_d_database
}

run_tests_for() {
    local service="$1"
    local database="$TEST_DATABASE"

    if [[ "$service" == "service-d" ]]; then
        database="$SERVICE_D_TEST_DATABASE"
    fi

    if [[ "$service" == "service-e" ]]; then
        echo "Running tests for service-e..."
        docker compose run --rm --no-deps \
            -e APP_ENV=test \
            -e DATABASE_URL="mysql://${MYSQL_USER}:${MYSQL_PASSWORD}@${MYSQL_HOST}:${MYSQL_PORT}/${TEST_DATABASE}?serverVersion=8.0.32&charset=utf8mb4" \
            service-e php vendor/bin/phpunit --configuration phpunit.xml.dist

        return
    fi

    echo "Running tests for ${service}..."
    artisan "$service" "$database" test --env=testing
}

ensure_supported_mode

cd "$ROOT_DIR"

if [[ "$MODE" == "prepare" ]]; then
    prepare_all_databases
    exit 0
fi

if [[ "$SKIP_PREPARE" == false && "$MODE" != "all" ]]; then
    if [[ "$MODE" == "service-d" ]]; then
        prepare_service_d_database
    else
        prepare_database
    fi
fi

case "$MODE" in
    all)
        if [[ "$SKIP_PREPARE" == false ]]; then
            prepare_all_databases
        fi

        for service in main-app service-a service-b service-c service-d service-e; do
            run_tests_for "$service"
        done
        ;;
    main-app|service-a|service-b|service-c|service-d|service-e)
        run_tests_for "$MODE"
        ;;
esac
