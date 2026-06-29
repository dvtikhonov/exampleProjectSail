#!/usr/bin/env bash
# Быстрая диагностика: cloudflared или VPS для MAX mini-app (без interstitial fxTunnel).

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PORT="${SERVICE_C_PORT:-8083}"
LOCAL_URL="http://127.0.0.1:${PORT}"

echo "=== MAX tunnel check (service-c :${PORT}) ==="
echo

code_local="$(curl -s -o /dev/null -w '%{http_code}' --connect-timeout 3 "${LOCAL_URL}" 2>/dev/null || echo 000)"
if [[ "${code_local}" =~ ^(200|301|302|404)$ ]]; then
    echo "[OK] Локальный service-c: ${LOCAL_URL} (HTTP ${code_local})"
else
    echo "[!!] service-c недоступен: ${LOCAL_URL} (HTTP ${code_local})"
    echo "     docker compose up -d service-c"
fi

echo
code_cf="$(curl -s -o /dev/null -w '%{http_code}' --connect-timeout 8 --max-time 12 \
    -X POST https://api.trycloudflare.com/tunnel 2>/dev/null || echo 000)"
if [[ "${code_cf}" != "000" ]]; then
    echo "[OK] cloudflared Quick Tunnel доступен (api.trycloudflare.com → HTTP ${code_cf})"
    echo "     ./scripts/cloudflared-tunnel.sh service-c run"
    echo "     Без interstitial — подходит для MAX mini-app."
else
    echo "[--] cloudflared недоступен (типично для РФ без VPN)"
    echo "     Попробуйте VPN + ./scripts/cloudflared-tunnel.sh service-c run"
fi

echo
if [[ -n "${VPS_HOST:-}" && -n "${VPS_DOMAIN:-}" ]]; then
    code_vps="$(curl -s -o /dev/null -w '%{http_code}' --connect-timeout 8 --max-time 15 \
        "https://${VPS_DOMAIN}/" 2>/dev/null || echo 000)"
    if [[ "${code_vps}" =~ ^(200|301|302|404)$ ]]; then
        echo "[OK] VPS ${VPS_DOMAIN} отвечает (HTTP ${code_vps})"
        echo "     MAX_WEBHOOK_URL=https://${VPS_DOMAIN}/api/webhooks/max"
        echo "     MAX_MINI_APP_URL=https://${VPS_DOMAIN}/max-app"
    else
        echo "[!!] VPS ${VPS_DOMAIN} не отвечает (HTTP ${code_vps})"
        echo "     Проверьте: DNS, nginx, SSH tunnel (./scripts/vps-tunnel.sh run)"
    fi
else
    echo "[--] VPS не настроен (задайте VPS_HOST и VPS_DOMAIN для проверки)"
    echo "     ./scripts/vps-tunnel.sh check"
fi

echo
echo "=== Рекомендация ==="
if [[ "${code_cf}" != "000" ]]; then
    echo "Используйте cloudflared — быстрее всего для dev."
elif [[ -n "${VPS_HOST:-}" ]]; then
    echo "Используйте VPS-туннель — стабильный URL без interstitial."
else
    echo "1) VPN + cloudflared  2) VPS + ./scripts/vps-tunnel.sh"
fi
