#!/usr/bin/env bash
# Inventory Illuminate\ / App\Models\ / Laravel helper+facade leaks in core layers:
# app/Services, app/Contracts, app/DTO, app/Enums, app/Exceptions.
# Helpers/facades: config('…'), event(…), DB::, Log::, Storage::, Cache::
# Usage:
#   bash scripts/architecture-leak-inventory.sh              # print current leaks
#   bash scripts/architecture-leak-inventory.sh --write-baseline
#   bash scripts/architecture-leak-inventory.sh --check      # compare to baseline (CI)
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

BASELINE="${ROOT}/tests/Architecture/baselines/core-illuminate-models-leaks.txt"
MODE="list"
# Core dirs scanned for Illuminate / Eloquent / helper+facade leaks.
CORE_SCAN_DIRS=(app/Services app/Contracts app/DTO app/Enums app/Exceptions)

case "${1:-}" in
    --write-baseline) MODE="write" ;;
    --check) MODE="check" ;;
    -h|--help)
        sed -n '2,8p' "$0"
        exit 0
        ;;
    "")
        ;;
    *)
        echo "Unknown option: $1" >&2
        exit 1
        ;;
esac

collect_leaks() {
    local tmp
    tmp="$(mktemp)"

    {
        grep -rl 'App\\Models' "${CORE_SCAN_DIRS[@]}" --include='*.php' 2>/dev/null || true
        grep -rl 'Illuminate\\' "${CORE_SCAN_DIRS[@]}" --include='*.php' 2>/dev/null || true
        # Laravel config() helper (string key); avoids method names like ->config()
        grep -rlE 'config[[:space:]]*\([[:space:]]*['\''"]' "${CORE_SCAN_DIRS[@]}" --include='*.php' 2>/dev/null || true
        grep -rlE '\bevent[[:space:]]*\(' "${CORE_SCAN_DIRS[@]}" --include='*.php' 2>/dev/null || true
        grep -rlE '\b(DB|Log|Storage|Cache)::' "${CORE_SCAN_DIRS[@]}" --include='*.php' 2>/dev/null || true
    } | sed 's|^\./||' | sort -u >"$tmp"

    cat "$tmp"
    rm -f "$tmp"
}

read_baseline() {
    if [[ ! -f "$BASELINE" ]]; then
        echo "ERROR: baseline missing: ${BASELINE#${ROOT}/}" >&2
        exit 1
    fi

    grep -vE '^\s*(#|$)' "$BASELINE" | sort -u
}

case "$MODE" in
    list)
        mapfile -t leaks < <(collect_leaks)
        echo "Core layer leaks (Illuminate\\ / App\\Models\\ / helpers+facades): ${#leaks[@]}"
        printf '%s\n' "${leaks[@]}"
        ;;
    write)
        {
            cat <<'HDR'
# Baseline: known Illuminate\ / App\Models\ / helper+facade leaks in core layers
# (app/Services, app/Contracts, app/DTO, app/Enums, app/Exceptions).
# Helpers/facades: config('…'), event(…), DB::, Log::, Storage::, Cache::
# Phase 0 inventory for Laravel core isolation. Remove a path when the file is cleaned.
# Do not add new paths — fix the leak instead (ports in Contracts/Shared, adapters in Infrastructure/Laravel).
# Update via: bash scripts/architecture-leak-inventory.sh --write-baseline
HDR
            collect_leaks
        } >"$BASELINE"
        count="$(grep -cvE '^\s*(#|$)' "$BASELINE" || true)"
        echo "Wrote baseline (${count} paths): ${BASELINE#${ROOT}/}"
        ;;
    check)
        mapfile -t current < <(collect_leaks)
        mapfile -t allowed < <(read_baseline)

        declare -A allowed_set=()
        for path in "${allowed[@]}"; do
            allowed_set["$path"]=1
        done

        declare -A current_set=()
        for path in "${current[@]}"; do
            current_set["$path"]=1
        done

        new_leaks=()
        stale=()

        for path in "${current[@]}"; do
            if [[ -z "${allowed_set[$path]+x}" ]]; then
                new_leaks+=("$path")
            fi
        done

        for path in "${allowed[@]}"; do
            if [[ -z "${current_set[$path]+x}" ]]; then
                stale+=("$path")
            fi
        done

        status=0

        if [[ ${#new_leaks[@]} -gt 0 ]]; then
            status=1
            echo "ERROR: new Illuminate\\ / App\\Models\\ / helper+facade leaks in core layers (not in baseline):"
            printf '  %s\n' "${new_leaks[@]}"
            echo "Fix the leak — do not extend the baseline."
            echo ""
        fi

        if [[ ${#stale[@]} -gt 0 ]]; then
            status=1
            echo "ERROR: baseline is stale (file no longer leaks — remove from baseline):"
            printf '  %s\n' "${stale[@]}"
            echo "Run: bash scripts/architecture-leak-inventory.sh --write-baseline"
            echo ""
        fi

        if [[ "$status" -ne 0 ]]; then
            echo "Core layer isolation check failed."
            exit 1
        fi

        echo "Core layer isolation check passed (baseline: ${#allowed[@]} known leak(s), current: ${#current[@]})."
        ;;
esac
