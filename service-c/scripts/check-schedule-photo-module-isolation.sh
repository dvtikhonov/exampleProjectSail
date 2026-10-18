#!/usr/bin/env bash
# Fail if Eloquent models, Laravel helpers/facades, Illuminate imports,
# or Food/Max application types leak into SchedulePhoto Domain/Application
# (scanned recursively).
# Infrastructure / Interface / Providers are adapters — not scanned.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

violations=0

check_pattern() {
    local dir="$1"
    local label="$2"
    local pattern="$3"
    local description="$4"

    if [[ ! -d "$dir" ]]; then
        return
    fi

    while IFS= read -r file; do
        [[ -z "$file" ]] && continue
        echo "ERROR: ${description} in ${label}: ${file#${ROOT}/}"
        violations=$((violations + 1))
    done < <(grep -rlE "$pattern" "$dir" --include='*.php' 2>/dev/null || true)
}

check_dir() {
    local dir="$1"
    local label="$2"

    check_pattern "$dir" "$label" 'App\\Models' 'App\Models'
    check_pattern "$dir" "$label" 'config[[:space:]]*\([[:space:]]*['\''"]' 'config() helper'
    check_pattern "$dir" "$label" '\bevent[[:space:]]*\(' 'event() helper'
    check_pattern "$dir" "$label" '\bDB::' 'DB:: facade'
    check_pattern "$dir" "$label" '\bLog::' 'Log:: facade'
    check_pattern "$dir" "$label" '\bStorage::' 'Storage:: facade'
    check_pattern "$dir" "$label" '\bCache::' 'Cache:: facade'
    check_pattern "$dir" "$label" 'use Illuminate\\' 'use Illuminate\'
    # Food/Max stay in Infrastructure adapters; domain dirs use module ports and DTOs.
    check_pattern "$dir" "$label" 'App\\Exceptions\\Food\\' 'App\Exceptions\Food\'
    check_pattern "$dir" "$label" 'App\\DTO\\Food\\' 'App\DTO\Food\'
    check_pattern "$dir" "$label" 'App\\Enums\\Food\\' 'App\Enums\Food\'
    check_pattern "$dir" "$label" 'App\\Contracts\\Food\\' 'App\Contracts\Food\'
    check_pattern "$dir" "$label" 'App\\Contracts\\Max\\' 'App\Contracts\Max\'
}

check_dir "app/Modules/SchedulePhoto/Domain" "app/Modules/SchedulePhoto/Domain"
check_dir "app/Modules/SchedulePhoto/Application" "app/Modules/SchedulePhoto/Application"

if [[ "$violations" -gt 0 ]]; then
    echo ""
    echo "SchedulePhoto module isolation check failed: ${violations} violation(s)."
    echo "Move Eloquent/Facades/helpers/Food/Max out of SchedulePhoto Domain/Application; use ports and Infrastructure/Interface adapters."
    exit 1
fi

echo "SchedulePhoto module isolation check passed."
