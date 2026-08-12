#!/usr/bin/env bash
# Deep load-test preflight for service-h (food-order PROFILE=deep).
# Exit 0 if all required checks pass; exit 1 if any required check fails.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SERVICE_H_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
REPO_ROOT="$(cd "${SERVICE_H_DIR}/.." && pwd)"

MIN_TOKENS=105
FPM_CONF="/usr/local/etc/php-fpm.d/zzz-www.conf"

# Preserve already-exported values; .env must not clobber them.
_pre_base_url="${BASE_URL-}"
_pre_tokens_file="${TOKENS_FILE-}"

if [[ -f "${SERVICE_H_DIR}/.env" ]]; then
  set -a
  # shellcheck disable=SC1091
  source "${SERVICE_H_DIR}/.env"
  set +a
fi

if [[ -n "${_pre_base_url}" ]]; then
  BASE_URL="${_pre_base_url}"
fi
if [[ -n "${_pre_tokens_file}" ]]; then
  TOKENS_FILE="${_pre_tokens_file}"
fi
unset _pre_base_url _pre_tokens_file

# Defaults after .env
BASE_URL="${BASE_URL:-http://localhost:8083}"
BASE_URL="${BASE_URL%/}"
TOKENS_FILE="${TOKENS_FILE:-}"

# Resolve TOKENS_FILE to an absolute/existing path when possible.
resolve_tokens_file() {
  local tf="${1:-}"
  if [[ -z "${tf}" ]]; then
    printf '%s\n' "${SERVICE_H_DIR}/tokens.json"
    return 0
  fi
  if [[ "${tf}" == /* ]]; then
    printf '%s\n' "${tf}"
    return 0
  fi
  if [[ -f "${SERVICE_H_DIR}/${tf}" ]]; then
    printf '%s\n' "${SERVICE_H_DIR}/${tf}"
    return 0
  fi
  if [[ -f "${SCRIPT_DIR}/${tf}" ]]; then
    printf '%s\n' "${SCRIPT_DIR}/${tf}"
    return 0
  fi
  if [[ -f "${PWD}/${tf}" ]]; then
    printf '%s\n' "${PWD}/${tf}"
    return 0
  fi
  local base
  base="$(basename -- "${tf}")"
  if [[ "${base}" == "tokens.json" && -f "${SERVICE_H_DIR}/tokens.json" ]]; then
    printf '%s\n' "${SERVICE_H_DIR}/tokens.json"
    return 0
  fi
  printf '%s\n' "${SERVICE_H_DIR}/${tf}"
}

TOKENS_FILE="$(resolve_tokens_file "${TOKENS_FILE}")"


failures=0
warnings=0

pass() { echo "[PASS] $*"; }
fail() { echo "[FAIL] $*"; failures=$((failures + 1)); }
warn() { echo "[WARN] $*"; warnings=$((warnings + 1)); }
info() { echo "[INFO] $*"; }

info "repo root: ${REPO_ROOT}"
info "BASE_URL=${BASE_URL}"
info "TOKENS_FILE=${TOKENS_FILE}"

# --- 1. Container service-c running ---
if ! command -v docker >/dev/null 2>&1; then
  fail "docker not found in PATH"
else
  status="$(
    cd "${REPO_ROOT}" && docker compose ps --status running --format '{{.Service}}' 2>/dev/null \
      | grep -x 'service-c' || true
  )"
  if [[ "${status}" == "service-c" ]]; then
    pass "container service-c is running"
  else
    running="$(
      cd "${REPO_ROOT}" && docker compose ps service-c 2>/dev/null | grep -Ei 'running|up' || true
    )"
    if [[ -n "${running}" ]]; then
      pass "container service-c is running"
    else
      fail "container service-c is not running (docker compose from monorepo root)"
    fi
  fi
fi

# --- 2-3. FPM pool settings inside service-c ---
fpm_grep() {
  local pattern="$1"
  (cd "${REPO_ROOT}" && docker compose exec -T service-c grep -E "${pattern}" "${FPM_CONF}" 2>/dev/null) || true
}

check_fpm_kv() {
  local key="$1"
  local expected="$2"
  local line
  line="$(fpm_grep "^[[:space:]]*${key}[[:space:]]*=")"
  if echo "${line}" | grep -Eq "^[[:space:]]*${key}[[:space:]]*=[[:space:]]*${expected}([[:space:]]|$)"; then
    pass "FPM ${key}=${expected}"
  else
    fail "FPM expected ${key}=${expected} in ${FPM_CONF}; got: ${line:-<empty/missing>}"
  fi
}

if command -v docker >/dev/null 2>&1; then
  # Local deep headroom (outcome A): docker-compose.yml PHP_FPM_* → 200/25/12/50 + backlog/max_requests
  check_fpm_kv "pm.max_children" "200"
  check_fpm_kv "pm.start_servers" "25"
  check_fpm_kv "pm.min_spare_servers" "12"
  check_fpm_kv "pm.max_spare_servers" "50"
  check_fpm_kv "listen.backlog" "1024"
  check_fpm_kv "pm.max_requests" "500"
fi

# --- 4. HTTP GET BASE_URL/ -> 2xx

if command -v curl >/dev/null 2>&1; then
  http_code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 10 "${BASE_URL}/" 2>/dev/null || echo '000')"
  if [[ "${http_code}" =~ ^2[0-9][0-9]$ ]]; then
    pass "HTTP GET ${BASE_URL}/ -> ${http_code}"
  else
    fail "HTTP GET ${BASE_URL}/ expected 2xx, got ${http_code}"
  fi
else
  fail "curl not found; cannot check HTTP ${BASE_URL}/"
fi

# --- 5. Tokens file exists and count >= MIN_TOKENS ---
if [[ ! -f "${TOKENS_FILE}" ]]; then
  fail "tokens file not found: ${TOKENS_FILE}"
else
  set +e
  token_count="$(python3 - "${TOKENS_FILE}" <<'PY'
import json, sys
path = sys.argv[1]
with open(path, encoding="utf-8") as f:
    data = json.load(f)

def tokens_from(obj):
    if isinstance(obj, list):
        out = []
        for item in obj:
            if isinstance(item, str) and item.strip():
                out.append(item)
            elif isinstance(item, dict):
                t = item.get("token")
                if isinstance(t, str) and t.strip():
                    out.append(t)
        return out
    if isinstance(obj, dict):
        if "tokens" in obj:
            return tokens_from(obj["tokens"])
        t = obj.get("token")
        if isinstance(t, str) and t.strip():
            return [t]
    return []

print(len(tokens_from(data)))
PY
)"
  py_rc=$?
  set -e
  if [[ $py_rc -ne 0 || -z "${token_count}" ]]; then
    fail "failed to parse tokens from ${TOKENS_FILE} (python3/json)"
  elif [[ "${token_count}" -ge "${MIN_TOKENS}" ]]; then
    pass "tokens count ${token_count} >= ${MIN_TOKENS} (${TOKENS_FILE})"
  else
    fail "tokens count ${token_count} < ${MIN_TOKENS} (${TOKENS_FILE})"
  fi
fi

# --- 6. Optional warn: MAX_MESSENGER_DRIVER in service-c/.env ---
SERVICE_C_ENV="${REPO_ROOT}/service-c/.env"
if [[ -f "${SERVICE_C_ENV}" ]]; then
  driver_line="$(grep -E '^[[:space:]]*MAX_MESSENGER_DRIVER=' "${SERVICE_C_ENV}" 2>/dev/null | tail -n1 || true)"
  if [[ -n "${driver_line}" ]]; then
    driver_val="${driver_line#*=}"
    driver_val="${driver_val%%#*}"
    driver_val="$(echo "${driver_val}" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//")"
    if [[ "${driver_val}" != "null" ]]; then
      warn "MAX_MESSENGER_DRIVER=${driver_val} in service-c/.env (expected null for deep load); continuing"
    else
      pass "MAX_MESSENGER_DRIVER=null"
    fi
  else
    info "MAX_MESSENGER_DRIVER not set in service-c/.env"
  fi
else
  info "service-c/.env not found; skip MAX_MESSENGER_DRIVER check"
fi

echo
if [[ "${failures}" -gt 0 ]]; then
  echo "Preflight FAILED: ${failures} check(s) failed (${warnings} warning(s))."
  exit 1
fi

echo "Preflight OK (${warnings} warning(s))."
exit 0
