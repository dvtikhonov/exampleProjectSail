#!/usr/bin/env bash
# Run food_order_flow.js; for PROFILE=deep (default) run preflight first.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROFILE="${PROFILE:-deep}"

if [[ "${PROFILE}" == "deep" ]]; then
  bash "${SCRIPT_DIR}/preflight_deep.sh"
fi

export PROFILE
exec k6 run --out influxdb=http://localhost:8089/k6 "${SCRIPT_DIR}/food_order_flow.js"
