#!/usr/bin/env bash
# Держит VPS SSH reverse tunnel с автопереподключением (MAX web + mobile).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

TUNNEL_ENV="${ROOT}/scripts/vps-tunnel.env"
if [[ -f "${TUNNEL_ENV}" ]]; then
    # shellcheck disable=SC1090
    set -a
    source "${TUNNEL_ENV}"
    set +a
fi

VPS_HOST="${VPS_HOST:-}"
VPS_USER="${VPS_USER:-}"
VPS_DOMAIN="${VPS_DOMAIN:-}"
VPS_PORT="${VPS_PORT:-22}"
VPS_SSH_KEY="${VPS_SSH_KEY:-}"
REMOTE_BIND_PORT="${REMOTE_BIND_PORT:-18083}"
PORT="${SERVICE_C_PORT:-8083}"
SSH_OPTS="${SSH_OPTS:--o ServerAliveInterval=30 -o ServerAliveCountMax=3 -o ExitOnForwardFailure=yes}"

if [[ -z "${VPS_HOST}" || -z "${VPS_USER}" || -z "${VPS_DOMAIN}" ]]; then
    echo "Задайте VPS_HOST, VPS_USER, VPS_DOMAIN в scripts/vps-tunnel.env" >&2
    echo "  cp scripts/vps-tunnel.env.example scripts/vps-tunnel.env" >&2
    echo "  VPS_HOST=${VPS_HOST:-<пусто>} VPS_USER=${VPS_USER:-<пусто>} VPS_DOMAIN=${VPS_DOMAIN:-<пусто>}" >&2
    exit 1
fi

echo "VPS hybrid tunnel → https://${VPS_DOMAIN} (Ctrl+C для остановки)"
echo "Проверка: ./scripts/vps-tunnel.sh verify"
echo ""

"${ROOT}/scripts/vps-tunnel.sh" check || true
echo ""

run_once() {
    local key_path=""
    if [[ -n "${VPS_SSH_KEY}" ]]; then
        local expanded="${VPS_SSH_KEY/#\~/$HOME}"
        if [[ -f "${expanded}" ]]; then
            key_path="${expanded}"
        else
            echo "WARNING: VPS_SSH_KEY не найден: ${expanded}" >&2
            echo "  ./scripts/vps-tunnel.sh print-ssh-key-setup" >&2
        fi
    fi

    if command -v autossh >/dev/null 2>&1; then
        local -a args=(-M 0 -N)
        args+=(-p "${VPS_PORT}")
        if [[ -n "${key_path}" ]]; then
            args+=(-i "${key_path}")
        fi
        # shellcheck disable=SC2206
        args+=(${SSH_OPTS})
        args+=(-R "127.0.0.1:${REMOTE_BIND_PORT}:127.0.0.1:${PORT}")
        args+=("${VPS_USER}@${VPS_HOST}")

        AUTOSSH_GATETIME=0 autossh "${args[@]}"
        return $?
    fi

    "${ROOT}/scripts/vps-tunnel.sh" run
}

while true; do
    echo "[$(date '+%H:%M:%S')] Запуск туннеля ..."
    if run_once; then
        echo "[$(date '+%H:%M:%S')] Туннель завершился штатно."
    else
        echo "[$(date '+%H:%M:%S')] Туннель упал (код $?). Перезапуск через 5 с ..."
    fi
    sleep 5
done
