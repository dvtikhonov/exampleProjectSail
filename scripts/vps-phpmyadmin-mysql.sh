#!/usr/bin/env bash
# Создание MySQL-пользователя pma_admin для phpMyAdmin на VPS.
# MySQL установлен на хосте; phpMyAdmin в Docker подключается через host.docker.internal.
#
# Запускать НА VPS из корня репозитория (один раз или при смене PMA_MYSQL_PASSWORD):
#
#   # в корневом .env: PMA_MYSQL_PASSWORD, MYSQL_ROOT_PASSWORD
#   ./scripts/vps-phpmyadmin-mysql.sh apply
#   ./scripts/vps-phpmyadmin-mysql.sh check

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SQL_TEMPLATE="${ROOT_DIR}/scripts/mysql-create-pma-admin.sql"
ENV_FILE="${ROOT_DIR}/.env"

MYSQL_HOST="${MYSQL_HOST:-127.0.0.1}"
MYSQL_PORT="${MYSQL_PORT:-3306}"
MYSQL_ROOT_USER="${MYSQL_ROOT_USER:-root}"
PMA_MYSQL_USER="${PMA_MYSQL_USER:-pma_admin}"

usage() {
    cat <<EOF
Использование: $(basename "$0") <команда>

Команды:
  apply       — создать/обновить pma_admin и выдать права на sail_db, service_d_db
  check       — проверить пользователей, GRANT и вход pma_admin
  print-sql   — показать SQL (пароль замаскирован)
  dry-run     — как apply, но только вывести SQL без выполнения

Переменные (корневой .env или export):
  MYSQL_ROOT_PASSWORD   пароль root MySQL (обязателен для apply)
  PMA_MYSQL_PASSWORD    пароль pma_admin (обязателен для apply/check)
  MYSQL_HOST            хост MySQL (по умолчанию: 127.0.0.1)
  MYSQL_PORT            порт MySQL (по умолчанию: 3306)
  MYSQL_ROOT_USER       пользователь root (по умолчанию: root)
  PMA_MYSQL_USER        имя пользователя phpMyAdmin (по умолчанию: pma_admin)

Пример на VPS:
  cd ~/apps/exampleProjectSail
  # добавить в .env: PMA_MYSQL_PASSWORD=<сильный_пароль>
  ./scripts/vps-phpmyadmin-mysql.sh apply
  ./scripts/vps-phpmyadmin-mysql.sh check
EOF
}

load_env() {
    if [[ -f "${ENV_FILE}" ]]; then
        # shellcheck disable=SC1090
        set -a
        source "${ENV_FILE}"
        set +a
    fi

    MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD%$'\r'}"
    PMA_MYSQL_PASSWORD="${PMA_MYSQL_PASSWORD%$'\r'}"
    MYSQL_HOST="${MYSQL_HOST%$'\r'}"
    MYSQL_PORT="${MYSQL_PORT%$'\r'}"
}

require_root_password() {
    if [[ -z "${MYSQL_ROOT_PASSWORD:-}" ]]; then
        echo "Задайте MYSQL_ROOT_PASSWORD в ${ENV_FILE} или export." >&2
        exit 1
    fi
}

require_pma_password() {
    if [[ -z "${PMA_MYSQL_PASSWORD:-}" ]]; then
        echo "Задайте PMA_MYSQL_PASSWORD в ${ENV_FILE} или export." >&2
        exit 1
    fi
}

require_mysql_client() {
    if ! command -v mysql >/dev/null 2>&1; then
        echo "Клиент mysql не найден. Установите: sudo apt-get install -y mysql-client" >&2
        exit 1
    fi
}

require_sql_template() {
    if [[ ! -f "${SQL_TEMPLATE}" ]]; then
        echo "Не найден шаблон: ${SQL_TEMPLATE}" >&2
        exit 1
    fi
}

escape_mysql_string() {
    printf '%s' "$1" | sed "s/'/''/g"
}

render_sql() {
    local password_escaped
    password_escaped="$(escape_mysql_string "${PMA_MYSQL_PASSWORD}")"
    PMA_ESCAPED="${password_escaped}" perl -pe \
        'BEGIN { $p = $ENV{PMA_ESCAPED} // die "PMA_ESCAPED is not set\n" } s/__PMA_MYSQL_PASSWORD__/$p/g' \
        "${SQL_TEMPLATE}"
}

render_sql_redacted() {
    sed 's/__PMA_MYSQL_PASSWORD__/***REDACTED***/g' "${SQL_TEMPLATE}"
}

mysql_root() {
    mysql \
        -h "${MYSQL_HOST}" \
        -P "${MYSQL_PORT}" \
        -u "${MYSQL_ROOT_USER}" \
        -p"${MYSQL_ROOT_PASSWORD}" \
        --batch \
        --skip-column-names \
        "$@"
}

mysql_pma() {
    mysql \
        -h "${MYSQL_HOST}" \
        -P "${MYSQL_PORT}" \
        -u "${PMA_MYSQL_USER}" \
        -p"${PMA_MYSQL_PASSWORD}" \
        --batch \
        --skip-column-names \
        "$@"
}

cmd_print_sql() {
    require_sql_template
    render_sql_redacted
}

cmd_dry_run() {
    load_env
    require_sql_template
    require_pma_password
    echo "# dry-run: SQL для ${PMA_MYSQL_USER} (пароль не выводится)"
    render_sql_redacted
}

cmd_apply() {
    load_env
    require_sql_template
    require_root_password
    require_pma_password
    require_mysql_client

    echo "Применение SQL для ${PMA_MYSQL_USER}@${MYSQL_HOST}:${MYSQL_PORT}..."

    if ! render_sql | mysql_root; then
        echo "Ошибка выполнения SQL. Проверьте MYSQL_ROOT_PASSWORD и доступность MySQL." >&2
        exit 1
    fi

    echo "Готово: пользователь ${PMA_MYSQL_USER} создан/обновлён."
    echo "Права: sail_db.*, service_d_db.* (без GRANT/SUPER/FILE и системных БД)."
    echo "Дальше: docker compose up -d phpmyadmin && ./scripts/vps-phpmyadmin.sh all"
}

cmd_check() {
    load_env
    require_pma_password
    require_mysql_client

    echo "=== MySQL ${MYSQL_HOST}:${MYSQL_PORT} ==="
    if ! mysqladmin ping -h "${MYSQL_HOST}" -P "${MYSQL_PORT}" -u "${PMA_MYSQL_USER}" -p"${PMA_MYSQL_PASSWORD}" --silent 2>/dev/null; then
        if [[ -n "${MYSQL_ROOT_PASSWORD:-}" ]]; then
            if ! mysqladmin ping -h "${MYSQL_HOST}" -P "${MYSQL_PORT}" -u "${MYSQL_ROOT_USER}" -p"${MYSQL_ROOT_PASSWORD}" --silent 2>/dev/null; then
                echo "ERROR: MySQL недоступен на ${MYSQL_HOST}:${MYSQL_PORT}" >&2
                exit 1
            fi
            echo "MySQL доступен (ping root OK)"
        else
            echo "ERROR: вход pma_admin не удался; задайте MYSQL_ROOT_PASSWORD для диагностики." >&2
            exit 1
        fi
    else
        echo "Вход pma_admin: OK"
    fi

    echo
    echo "=== Пользователи ${PMA_MYSQL_USER} ==="
    if [[ -n "${MYSQL_ROOT_PASSWORD:-}" ]]; then
        mysql_root -e "SELECT user, host FROM mysql.user WHERE user='${PMA_MYSQL_USER}' ORDER BY host;" \
            || echo "WARNING: не удалось прочитать mysql.user (нужен root)"
    else
        echo "(задайте MYSQL_ROOT_PASSWORD для просмотра mysql.user)"
    fi

    echo
    echo "=== GRANT (show grants) ==="
    for host in localhost '172.%.%'; do
        echo "--- ${PMA_MYSQL_USER}@${host} ---"
        if [[ -n "${MYSQL_ROOT_PASSWORD:-}" ]]; then
            mysql_root -e "SHOW GRANTS FOR '${PMA_MYSQL_USER}'@'${host}';" 2>/dev/null \
                || echo "  (пользователь @${host} не найден)"
        fi
    done

    echo
    echo "=== Доступ к прикладным БД ==="
    for db in sail_db service_d_db; do
        if mysql_pma -e "SELECT 1" "${db}" >/dev/null 2>&1; then
            echo "  ${db}: OK"
        else
            echo "  ${db}: FAIL (нет доступа или БД не существует)"
        fi
    done

    echo
    echo "=== Системные БД (должны быть недоступны) ==="
    for db in mysql information_schema; do
        if mysql_pma -e "SELECT 1" "${db}" >/dev/null 2>&1; then
            echo "  ${db}: WARNING — доступ есть (ожидался отказ)"
        else
            echo "  ${db}: OK (доступ запрещён)"
        fi
    done
}

main() {
    local action="${1:-check}"

    case "${action}" in
        apply)
            cmd_apply
            ;;
        check)
            cmd_check
            ;;
        print-sql)
            cmd_print_sql
            ;;
        dry-run)
            cmd_dry_run
            ;;
        -h|--help|help)
            usage
            ;;
        *)
            echo "Неизвестная команда: ${action}" >&2
            usage >&2
            exit 1
            ;;
    esac
}

main "$@"
