#!/usr/bin/env bash
# Первичная подготовка VPS (фаза 0 плана миграции монорепозитория).
# Запускать НА VPS под пользователем deploy (по умолчанию developer):
#
#   curl -fsSL .../raw/main/scripts/vps-bootstrap.sh | bash -s -- all
#   # или после git clone:
#   ./scripts/vps-bootstrap.sh all
#
# После docker: перелогиниться (newgrp docker или новая SSH-сессия).

set -euo pipefail

SWAP_FILE="${SWAP_FILE:-/swapfile}"
SWAP_SIZE="${SWAP_SIZE:-2G}"
DEPLOY_USER="${DEPLOY_USER:-developer}"
DEPLOY_KEY_COMMENT="${DEPLOY_KEY_COMMENT:-vps-deploy}"

# При sudo ./vps-bootstrap.sh HOME=/root — ключ и authorized_keys должны быть у DEPLOY_USER.
effective_deploy_home() {
    if [[ -n "${DEPLOY_HOME:-}" ]]; then
        echo "${DEPLOY_HOME}"
        return
    fi
    if [[ "${EUID}" -eq 0 ]]; then
        getent passwd "${DEPLOY_USER}" | cut -d: -f6
        return
    fi
    echo "${HOME}"
}

deploy_home="$(effective_deploy_home)"
DEPLOY_KEY_PATH="${DEPLOY_KEY_PATH:-${deploy_home}/.ssh/github_deploy}"

usage() {
    cat <<EOF
Использование: $(basename "$0") <команда>

Команды:
  all                 — полная подготовка (packages → swap → docker → ufw → ssh-deploy-key)
  packages            — apt update/upgrade, git, curl, ufw, fail2ban
  swap                — swap ${SWAP_SIZE} (идемпотентно)
  docker              — Docker Engine + Compose plugin, пользователь в группе docker
  ufw                 — firewall: OpenSSH, 80/tcp, 443/tcp
  ssh-deploy-key      — ED25519-ключ для GitHub Actions CD (DEPLOY_SSH_KEY)
  check               — проверить состояние компонентов
  print-github-secrets — подсказка по секретам GitHub Actions

Переменные:
  DEPLOY_USER         SSH-пользователь (по умолчанию: developer)
  DEPLOY_KEY_PATH     путь к ключу CD (по умолчанию: ~/.ssh/github_deploy)
  SWAP_FILE           файл swap (по умолчанию: /swapfile)
  SWAP_SIZE           размер swap (по умолчанию: 2G)

Пример на VPS:
  sudo ./scripts/vps-bootstrap.sh all
  ./scripts/vps-bootstrap.sh check
  ./scripts/vps-bootstrap.sh print-github-secrets
EOF
}

require_sudo() {
    if [[ "${EUID}" -eq 0 ]]; then
        return 0
    fi
    if command -v sudo >/dev/null 2>&1; then
        return 0
    fi
    echo "Нужны права root или sudo." >&2
    exit 1
}

run_as_root() {
    if [[ "${EUID}" -eq 0 ]]; then
        "$@"
    else
        sudo "$@"
    fi
}

log_step() {
    echo
    echo "==> $*"
}

cmd_packages() {
    require_sudo
    log_step "Обновление пакетов и установка базовых утилит"
    run_as_root apt-get update
    run_as_root DEBIAN_FRONTEND=noninteractive apt-get upgrade -y
    run_as_root DEBIAN_FRONTEND=noninteractive apt-get install -y \
        git curl ufw fail2ban ca-certificates gnupg
    echo "Пакеты установлены."
}

swap_is_active() {
    swapon --show 2>/dev/null | grep -q "${SWAP_FILE}" || \
        grep -q "^${SWAP_FILE}[[:space:]]" /proc/swaps 2>/dev/null
}

fstab_has_swap() {
    grep -q "^${SWAP_FILE}[[:space:]]" /etc/fstab 2>/dev/null
}

cmd_swap() {
    require_sudo

    if swap_is_active; then
        echo "Swap уже активен: ${SWAP_FILE}"
        swapon --show 2>/dev/null || true
        return 0
    fi

    log_step "Создание swap ${SWAP_SIZE} в ${SWAP_FILE}"

    if [[ ! -f "${SWAP_FILE}" ]]; then
        run_as_root fallocate -l "${SWAP_SIZE}" "${SWAP_FILE}"
        run_as_root chmod 600 "${SWAP_FILE}"
        run_as_root mkswap "${SWAP_FILE}"
    else
        echo "Файл ${SWAP_FILE} уже существует, пропускаю fallocate/mkswap."
    fi

    run_as_root swapon "${SWAP_FILE}"

    if ! fstab_has_swap; then
        echo "${SWAP_FILE} none swap sw 0 0" | run_as_root tee -a /etc/fstab >/dev/null
        echo "Запись добавлена в /etc/fstab."
    else
        echo "/etc/fstab уже содержит ${SWAP_FILE}."
    fi

    free -h
}

docker_installed() {
    command -v docker >/dev/null 2>&1
}

user_in_docker_group() {
    id -nG "${DEPLOY_USER}" 2>/dev/null | grep -qw docker
}

cmd_docker() {
    require_sudo

    if ! docker_installed; then
        log_step "Установка Docker Engine (get.docker.com)"
        curl -fsSL https://get.docker.com | run_as_root sh
    else
        echo "Docker уже установлен: $(docker --version)"
    fi

    if ! getent group docker >/dev/null 2>&1; then
        run_as_root groupadd docker
    fi

    if ! user_in_docker_group; then
        run_as_root usermod -aG docker "${DEPLOY_USER}"
        echo "Пользователь ${DEPLOY_USER} добавлен в группу docker."
        echo "Перелогиньтесь или выполните: newgrp docker"
    else
        echo "Пользователь ${DEPLOY_USER} уже в группе docker."
    fi

    if docker compose version >/dev/null 2>&1; then
        echo "Compose: $(docker compose version)"
    else
        echo "Предупреждение: docker compose plugin не найден." >&2
        exit 1
    fi
}

cmd_ufw() {
    require_sudo
    log_step "Настройка UFW (OpenSSH, 80/tcp, 443/tcp)"

    run_as_root ufw allow OpenSSH
    run_as_root ufw allow 80/tcp
    run_as_root ufw allow 443/tcp

    if run_as_root ufw status | grep -q "Status: active"; then
        echo "UFW уже включён."
    else
        run_as_root ufw --force enable
        echo "UFW включён."
    fi

    run_as_root ufw status verbose
}

run_as_deploy_user() {
    if [[ "${EUID}" -eq 0 && "$(id -un)" != "${DEPLOY_USER}" ]]; then
        run_as_root -u "${DEPLOY_USER}" -H "$@"
    else
        "$@"
    fi
}

ensure_ssh_dir() {
    local dir="${deploy_home}/.ssh"
    run_as_deploy_user mkdir -p "${dir}"
    run_as_deploy_user chmod 700 "${dir}"
}

authorized_keys_has_entry() {
    local pubkey="$1"
    local auth_keys="${deploy_home}/.ssh/authorized_keys"

    [[ -f "${auth_keys}" ]] || return 1
    grep -qF "${pubkey}" "${auth_keys}"
}

cmd_ssh_deploy_key() {
    ensure_ssh_dir

    if [[ -f "${DEPLOY_KEY_PATH}" ]]; then
        echo "Ключ уже существует: ${DEPLOY_KEY_PATH}"
    else
        log_step "Генерация ED25519-ключа для GitHub Actions CD (${DEPLOY_USER})"
        run_as_deploy_user ssh-keygen -t ed25519 -C "${DEPLOY_KEY_COMMENT}" -f "${DEPLOY_KEY_PATH}" -N ""
        run_as_deploy_user chmod 600 "${DEPLOY_KEY_PATH}"
        run_as_deploy_user chmod 644 "${DEPLOY_KEY_PATH}.pub"
        echo "Создан: ${DEPLOY_KEY_PATH}"
    fi

    local pubkey
    pubkey="$(cat "${DEPLOY_KEY_PATH}.pub")"

    if authorized_keys_has_entry "${pubkey}"; then
        echo "Публичный ключ уже в ${deploy_home}/.ssh/authorized_keys."
    else
        log_step "Добавление публичного ключа в authorized_keys"
        run_as_deploy_user bash -c 'echo "$1" >> "$2"' _ "${pubkey}" "${deploy_home}/.ssh/authorized_keys"
        run_as_deploy_user chmod 600 "${deploy_home}/.ssh/authorized_keys"
        echo "Ключ добавлен в authorized_keys."
    fi

    echo
    echo "--- Публичный ключ (authorized_keys / Deploy Key read-only для git clone) ---"
    cat "${DEPLOY_KEY_PATH}.pub"
    echo
    echo "--- Приватный ключ → GitHub Secret DEPLOY_SSH_KEY (скопируйте один раз) ---"
    echo "  gh secret set DEPLOY_SSH_KEY < ${DEPLOY_KEY_PATH}"
    echo "  # или вставьте содержимое файла в Settings → Secrets → Actions"
    echo
    echo "После настройки секрета проверьте:"
    echo "  ssh -i ${DEPLOY_KEY_PATH} ${DEPLOY_USER}@<VPS_IP> 'echo ok'"
}

check_packages() {
    local ok=0
    for bin in git curl ufw fail2ban-client; do
        if command -v "${bin}" >/dev/null 2>&1; then
            echo "[ok] ${bin}"
        else
            echo "[--] ${bin} — не установлен"
            ok=1
        fi
    done
    return "${ok}"
}

check_swap() {
    if swap_is_active; then
        echo "[ok] swap активен (${SWAP_FILE})"
        swapon --show 2>/dev/null | sed 's/^/      /'
        return 0
    fi
    echo "[--] swap не активен"
    return 1
}

check_docker() {
    local ok=0
    if docker_installed; then
        echo "[ok] $(docker --version)"
    else
        echo "[--] docker не установлен"
        ok=1
    fi
    if docker compose version >/dev/null 2>&1; then
        echo "[ok] $(docker compose version)"
    else
        echo "[--] docker compose недоступен"
        ok=1
    fi
    if user_in_docker_group; then
        echo "[ok] ${DEPLOY_USER} в группе docker"
    else
        echo "[--] ${DEPLOY_USER} не в группе docker (перелогиниться после usermod)"
        ok=1
    fi
    return "${ok}"
}

check_ufw() {
    if command -v ufw >/dev/null 2>&1 && sudo ufw status 2>/dev/null | grep -q "Status: active"; then
        echo "[ok] UFW активен"
        sudo ufw status | sed 's/^/      /'
        return 0
    fi
    echo "[--] UFW не активен или нет sudo"
    return 1
}

check_ssh_deploy_key() {
    if [[ -f "${DEPLOY_KEY_PATH}" && -f "${DEPLOY_KEY_PATH}.pub" ]]; then
        echo "[ok] ключ CD: ${DEPLOY_KEY_PATH}"
        if authorized_keys_has_entry "$(cat "${DEPLOY_KEY_PATH}.pub")"; then
            echo "[ok] pubkey в authorized_keys"
        else
            echo "[--] pubkey не найден в authorized_keys"
            return 1
        fi
        return 0
    fi
    echo "[--] ключ CD не создан (${DEPLOY_KEY_PATH})"
    return 1
}

cmd_check() {
    local failed=0
    log_step "Пакеты"
    check_packages || failed=1
    log_step "Swap"
    check_swap || failed=1
    log_step "Docker"
    check_docker || failed=1
    log_step "UFW"
    check_ufw || failed=1
    log_step "SSH-ключ для CD"
    check_ssh_deploy_key || failed=1
    log_step "Память"
    free -h
    echo
    if [[ "${failed}" -eq 0 ]]; then
        echo "Все проверки пройдены."
        return 0
    fi
    echo "Есть незавершённые пункты — выполните: $(basename "$0") all"
    return 1
}

cmd_print_github_secrets() {
    local host_ip
    host_ip="$(hostname -I 2>/dev/null | awk '{print $1}')"

    cat <<EOF
GitHub Actions → Settings → Secrets and variables → Actions:

  DEPLOY_HOST     = ${host_ip:-94.228.117.27}
  DEPLOY_USER     = ${DEPLOY_USER}
  DEPLOY_PATH     = /home/${DEPLOY_USER}/apps/exampleProjectSail
  DEPLOY_PORT     = 22
  DEPLOY_SSH_KEY  = содержимое ${DEPLOY_KEY_PATH} (приватный ключ)

Публичный URL (sslip.io): https://94-228-117-27.sslip.io

После clone репозитория на VPS:
  mkdir -p ~/apps && cd ~/apps
  git clone <repo-url> exampleProjectSail
  cd exampleProjectSail
  export COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml
  # настроить .env (MYSQL_ROOT_PASSWORD, MAIL_*, REVERB_ALLOWED_ORIGINS, ...)
  docker compose up -d

HTTPS (один раз, порты 80/443 — host nginx, не Docker):
  export VPS_DOMAIN=94-228-117-27.sslip.io
  export CERTBOT_EMAIL=your@email.com
  ./scripts/vps-nginx-ssl.sh all
  # в main-app/.env: APP_URL=https://94-228-117-27.sslip.io
EOF
}

cmd_all() {
    cmd_packages
    cmd_swap
    cmd_docker
    cmd_ufw
    cmd_ssh_deploy_key
    echo
    cmd_check || true
    echo
    cmd_print_github_secrets
}

main() {
    local action="${1:-}"

    case "${action}" in
        all)
            cmd_all
            ;;
        packages)
            cmd_packages
            ;;
        swap)
            cmd_swap
            ;;
        docker)
            cmd_docker
            ;;
        ufw)
            cmd_ufw
            ;;
        ssh-deploy-key)
            cmd_ssh_deploy_key
            ;;
        check)
            cmd_check
            ;;
        print-github-secrets)
            cmd_print_github_secrets
            ;;
        -h|--help|help|"")
            usage
            [[ -n "${action}" ]] || exit 0
            ;;
        *)
            echo "Неизвестная команда: ${action}" >&2
            usage >&2
            exit 1
            ;;
    esac
}

main "$@"
