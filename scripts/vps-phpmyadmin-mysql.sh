#!/usr/bin/env bash
# Создание MySQL-пользователя pma_admin для phpMyAdmin на VPS.
# MySQL установлен на хосте; phpMyAdmin в Docker подключается через host.docker.internal.
#
# Совместимость: MySQL 5.7 (Ubuntu 18.04, auth_socket, validate_password) и MySQL 8+.
#
# Запускать НА VPS из корня репозитория (один раз или при смене PMA_MYSQL_PASSWORD):
#
#   # в корневом .env: PMA_MYSQL_PASSWORD, MYSQL_ROOT_PASSWORD
#   ./scripts/vps-phpmyadmin-mysql.sh ensure-root-auth   # если root@localhost = auth_socket
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
MYSQL_DOCKER_HOST_PATTERN="${MYSQL_DOCKER_HOST_PATTERN:-172.%}"

usage() {
    cat <<EOF
Использование: $(basename "$0") <команда>

Команды:
  ensure-root-auth — настроить root для парольного входа (MySQL 5.7 auth_socket → mysql_native_password)
  apply            — создать/обновить pma_admin и выдать права на sail_db, service_d_db
  check            — проверить пользователей, GRANT и вход pma_admin
  print-sql        — показать SQL (пароль замаскирован)
  dry-run          — как apply, но только вывести SQL без выполнения

Переменные (корневой .env или export):
  MYSQL_ROOT_PASSWORD   пароль root MySQL (обязателен для apply / ensure-root-auth)
  PMA_MYSQL_PASSWORD    пароль pma_admin (обязателен для apply/check; должен пройти validate_password на 5.7)
  MYSQL_HOST            хост MySQL (по умолчанию: 127.0.0.1)
  MYSQL_PORT            порт MySQL (по умолчанию: 3306)
  MYSQL_ROOT_USER       пользователь root (по умолчанию: root)
  PMA_MYSQL_USER        имя пользователя phpMyAdmin (по умолчанию: pma_admin)
  MYSQL_DOCKER_HOST_PATTERN  host для Docker bridge (по умолчанию: 172.%)

MySQL 5.7 / Ubuntu 18.04:
  - root часто с plugin auth_socket — сначала: $(basename "$0") ensure-root-auth
  - PMA_MYSQL_PASSWORD: ≥8 символов, заглавная, цифра, спецсимвол (validate_password MEDIUM)

Пример на VPS:
  cd ~/apps/exampleProjectSail
  ./scripts/vps-phpmyadmin-mysql.sh ensure-root-auth
  ./scripts/vps-phpmyadmin-mysql.sh apply
  ./scripts/vps-phpmyadmin-mysql.sh check
EOF
}

require_sudo() {
    if [[ "${EUID}" -ne 0 ]] && ! sudo -n true 2>/dev/null; then
        echo "Нужны права sudo." >&2
        exit 1
    fi
}

run_root() {
    if [[ "${EUID}" -eq 0 ]]; then
        "$@"
    else
        sudo "$@"
    fi
}

load_env() {
    if [[ -f "${ENV_FILE}" ]]; then
        # shellcheck disable=SC1090
        set -a
        source "${ENV_FILE}"
        set +a
    fi

    MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-}"
    MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD%$'\r'}"
    PMA_MYSQL_PASSWORD="${PMA_MYSQL_PASSWORD:-}"
    PMA_MYSQL_PASSWORD="${PMA_MYSQL_PASSWORD%$'\r'}"
    MYSQL_HOST="${MYSQL_HOST:-127.0.0.1}"
    MYSQL_HOST="${MYSQL_HOST%$'\r'}"
    MYSQL_PORT="${MYSQL_PORT:-3306}"
    MYSQL_PORT="${MYSQL_PORT%$'\r'}"
    MYSQL_DOCKER_HOST_PATTERN="${MYSQL_DOCKER_HOST_PATTERN:-172.%}"
    MYSQL_DOCKER_HOST_PATTERN="${MYSQL_DOCKER_HOST_PATTERN%$'\r'}"
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

mysql_socket_query() {
    run_root mysql --batch --skip-column-names "$@"
}

root_localhost_plugin() {
    mysql_socket_query -N -e \
        "SELECT plugin FROM mysql.user WHERE user='${MYSQL_ROOT_USER}' AND host='localhost' LIMIT 1;" \
        2>/dev/null || true
}

mysql_root_tcp_ok() {
    [[ -n "${MYSQL_ROOT_PASSWORD:-}" ]] || return 1
    mysqladmin ping \
        -h "${MYSQL_HOST}" \
        -P "${MYSQL_PORT}" \
        -u "${MYSQL_ROOT_USER}" \
        -p"${MYSQL_ROOT_PASSWORD}" \
        --silent >/dev/null 2>&1
}

mysql_root_tcp() {
    mysql \
        -h "${MYSQL_HOST}" \
        -P "${MYSQL_PORT}" \
        -u "${MYSQL_ROOT_USER}" \
        -p"${MYSQL_ROOT_PASSWORD}" \
        --batch \
        --skip-column-names \
        "$@"
}

# Выполнение SQL/queries от root: TCP с паролем или sudo mysql (auth_socket).
mysql_root_run() {
    if mysql_root_tcp_ok; then
        mysql_root_tcp "$@"
        return
    fi

    local plugin
    plugin="$(root_localhost_plugin)"
    if [[ "${plugin}" == "auth_socket" ]] && run_root mysql -e "SELECT 1" >/dev/null 2>&1; then
        mysql_socket_query "$@"
        return
    fi

    echo "ERROR: нет доступа к MySQL как ${MYSQL_ROOT_USER}." >&2
    if [[ "${plugin}" == "auth_socket" ]]; then
        echo "  root@localhost использует auth_socket (типично для Ubuntu 18.04 / MySQL 5.7)." >&2
        echo "  Выполните: $(basename "$0") ensure-root-auth" >&2
    else
        echo "  Проверьте MYSQL_ROOT_PASSWORD и доступность MySQL на ${MYSQL_HOST}:${MYSQL_PORT}." >&2
    fi
    return 1
}

print_auth_socket_hint() {
    cat >&2 <<EOF
Подсказка (MySQL 5.7 / Ubuntu 18.04):
  sudo mysql -e "SELECT user, host, plugin FROM mysql.user WHERE user='root';"
  ./scripts/vps-phpmyadmin-mysql.sh ensure-root-auth
EOF
}

print_password_policy_hint() {
    cat >&2 <<EOF
Подсказка (validate_password на MySQL 5.7):
  PMA_MYSQL_PASSWORD должен быть достаточно сложным (часто: ≥8 символов, заглавная, цифра, спецсимвол).
  Проверка: $(basename "$0") check-policy
  Политика: mysql_root_run -e "SHOW VARIABLES LIKE 'validate_password%';"
EOF
}

check_pma_password_policy() {
    local escaped strength policy min_strength length count

    escaped="$(escape_mysql_string "${PMA_MYSQL_PASSWORD}")"

    count="$(mysql_root_run -N -e "SHOW VARIABLES LIKE 'validate_password%';" 2>/dev/null | wc -l | tr -d ' ')"
    if [[ "${count}" -eq 0 ]]; then
        return 0
    fi

    strength="$(mysql_root_run -N -e "SELECT VALIDATE_PASSWORD_STRENGTH('${escaped}');" 2>/dev/null || echo "0")"
    policy="$(mysql_root_run -N -e "SELECT @@validate_password_policy;" 2>/dev/null || echo "1")"
    length="$(mysql_root_run -N -e "SELECT @@validate_password_length;" 2>/dev/null || echo "8")"

    min_strength=50
    case "${policy}" in
        0|LOW) min_strength=25 ;;
        1|MEDIUM) min_strength=50 ;;
        2|STRONG) min_strength=75 ;;
    esac

    if [[ "${strength}" -lt "${min_strength}" ]] || [[ ${#PMA_MYSQL_PASSWORD} -lt ${length} ]]; then
        echo "ERROR: PMA_MYSQL_PASSWORD не проходит validate_password (strength=${strength}, нужно ≥${min_strength}, длина ≥${length})." >&2
        mysql_root_run -e "SHOW VARIABLES LIKE 'validate_password%';" 2>/dev/null >&2 || true
        print_password_policy_hint
        return 1
    fi
}

render_sql() {
    local password_escaped docker_host_escaped
    password_escaped="$(escape_mysql_string "${PMA_MYSQL_PASSWORD}")"
    docker_host_escaped="$(escape_mysql_string "${MYSQL_DOCKER_HOST_PATTERN}")"
    PMA_ESCAPED="${password_escaped}" \
    PMA_DOCKER_HOST="${docker_host_escaped}" \
    perl -pe '
        BEGIN {
            $p = $ENV{PMA_ESCAPED} // die "PMA_ESCAPED is not set\n";
            $h = $ENV{PMA_DOCKER_HOST} // die "PMA_DOCKER_HOST is not set\n";
        }
        s/__PMA_MYSQL_PASSWORD__/$p/g;
        s/__PMA_DOCKER_HOST__/$h/g;
    ' "${SQL_TEMPLATE}"
}

render_sql_redacted() {
    sed \
        -e 's/__PMA_MYSQL_PASSWORD__/***REDACTED***/g' \
        -e "s/__PMA_DOCKER_HOST__/${MYSQL_DOCKER_HOST_PATTERN}/g" \
        "${SQL_TEMPLATE}"
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
    load_env
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

cmd_check_policy() {
    load_env
    require_pma_password
    require_mysql_client

    if ! check_pma_password_policy; then
        exit 1
    fi
    echo "PMA_MYSQL_PASSWORD проходит validate_password."
}

cmd_ensure_root_auth() {
    load_env
    require_root_password
    require_mysql_client
    require_sudo

    local plugin version root_escaped

    if ! run_root mysql -e "SELECT 1" >/dev/null 2>&1; then
        echo "ERROR: sudo mysql недоступен — нужен локальный root через auth_socket или socket." >&2
        exit 1
    fi

    version="$(run_root mysql -N -e "SELECT VERSION();")"
    plugin="$(root_localhost_plugin)"
    root_escaped="$(escape_mysql_string "${MYSQL_ROOT_PASSWORD}")"

    echo "MySQL ${version}, root@localhost plugin: ${plugin:-unknown}"

    if [[ "${version}" =~ ^5\. ]]; then
        echo "Настройка mysql_native_password для MySQL 5.7..."
        run_root mysql <<SQL
ALTER USER '${MYSQL_ROOT_USER}'@'localhost'
  IDENTIFIED WITH mysql_native_password BY '${root_escaped}';
CREATE USER IF NOT EXISTS '${MYSQL_ROOT_USER}'@'${MYSQL_DOCKER_HOST_PATTERN}'
  IDENTIFIED BY '${root_escaped}';
GRANT ALL PRIVILEGES ON *.* TO '${MYSQL_ROOT_USER}'@'${MYSQL_DOCKER_HOST_PATTERN}' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL
    else
        echo "Настройка caching_sha2_password для MySQL 8+..."
        run_root mysql <<SQL
ALTER USER '${MYSQL_ROOT_USER}'@'localhost'
  IDENTIFIED WITH caching_sha2_password BY '${root_escaped}';
CREATE USER IF NOT EXISTS '${MYSQL_ROOT_USER}'@'${MYSQL_DOCKER_HOST_PATTERN}'
  IDENTIFIED BY '${root_escaped}';
GRANT ALL PRIVILEGES ON *.* TO '${MYSQL_ROOT_USER}'@'${MYSQL_DOCKER_HOST_PATTERN}' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL
    fi

    if mysql_root_tcp_ok; then
        echo "OK: вход root по TCP (${MYSQL_HOST}:${MYSQL_PORT}) работает."
    else
        echo "WARNING: после ensure-root-auth TCP-вход не удался — проверьте пароль в .env." >&2
        exit 1
    fi

    echo "Дальше: $(basename "$0") apply"
}

cmd_apply() {
    load_env
    require_sql_template
    require_root_password
    require_pma_password
    require_mysql_client

    if ! mysql_root_tcp_ok; then
        local plugin
        plugin="$(root_localhost_plugin)"
        if [[ "${plugin}" == "auth_socket" ]]; then
            echo "WARNING: root@localhost = auth_socket — apply через sudo mysql." >&2
            echo "  Рекомендуется сначала: $(basename "$0") ensure-root-auth" >&2
        fi
    fi

    if ! check_pma_password_policy; then
        exit 1
    fi

    echo "Применение SQL для ${PMA_MYSQL_USER}@${MYSQL_HOST}:${MYSQL_PORT}..."

    local sql_err
    sql_err="$(mktemp)"
    if ! render_sql | mysql_root_run 2>"${sql_err}"; then
        if grep -q "Access denied for user 'root'@'localhost'" "${sql_err}" 2>/dev/null \
            || grep -q "ERROR 1698" "${sql_err}" 2>/dev/null; then
            print_auth_socket_hint
        elif grep -q "ERROR 1819" "${sql_err}" 2>/dev/null \
            || grep -q "does not satisfy the current policy" "${sql_err}" 2>/dev/null; then
            print_password_policy_hint
        fi
        cat "${sql_err}" >&2
        rm -f "${sql_err}"
        echo "Ошибка выполнения SQL. Проверьте MYSQL_ROOT_PASSWORD, PMA_MYSQL_PASSWORD и доступность MySQL." >&2
        exit 1
    fi
    rm -f "${sql_err}"

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
        if mysql_root_tcp_ok || mysql_root_run -e "SELECT 1" >/dev/null 2>&1; then
            echo "MySQL доступен (ping root OK)"
        else
            echo "ERROR: MySQL недоступен на ${MYSQL_HOST}:${MYSQL_PORT}" >&2
            print_auth_socket_hint
            exit 1
        fi
    else
        echo "Вход pma_admin: OK"
    fi

    echo
    echo "=== root@localhost ==="
    local plugin
    plugin="$(root_localhost_plugin)"
    echo "  plugin: ${plugin:-unknown}"
    if [[ "${plugin}" == "auth_socket" ]]; then
        echo "  WARNING: auth_socket — Docker/скрипт ожидают парольный root. Нужен: $(basename "$0") ensure-root-auth"
    elif mysql_root_tcp_ok; then
        echo "  TCP root: OK"
    else
        echo "  TCP root: FAIL (проверьте MYSQL_ROOT_PASSWORD)"
    fi

    echo
    echo "=== Пользователи ${PMA_MYSQL_USER} ==="
    mysql_root_run -e "SELECT user, host, plugin FROM mysql.user WHERE user='${PMA_MYSQL_USER}' ORDER BY host;" \
        || echo "WARNING: не удалось прочитать mysql.user"

    echo
    echo "=== GRANT (show grants) ==="
    for host in localhost "${MYSQL_DOCKER_HOST_PATTERN}"; do
        echo "--- ${PMA_MYSQL_USER}@${host} ---"
        mysql_root_run -e "SHOW GRANTS FOR '${PMA_MYSQL_USER}'@'${host}';" 2>/dev/null \
            || echo "  (пользователь @${host} не найден)"
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
        ensure-root-auth)
            cmd_ensure_root_auth
            ;;
        apply)
            cmd_apply
            ;;
        check)
            cmd_check
            ;;
        check-policy)
            cmd_check_policy
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
