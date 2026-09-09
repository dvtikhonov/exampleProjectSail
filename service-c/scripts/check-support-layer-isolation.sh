#!/usr/bin/env bash
# Fail if Eloquent models, Laravel helpers/facades, or Illuminate imports
# leak into app/Support (must stay framework-free helpers).
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
    check_pattern "$dir" "$label" 'request[[:space:]]*\(' 'request() helper'
    check_pattern "$dir" "$label" '\bevent[[:space:]]*\(' 'event() helper'
    check_pattern "$dir" "$label" '\bDB::' 'DB:: facade'
    check_pattern "$dir" "$label" '\bLog::' 'Log:: facade'
    check_pattern "$dir" "$label" '\bStorage::' 'Storage:: facade'
    check_pattern "$dir" "$label" '\bCache::' 'Cache:: facade'
    check_pattern "$dir" "$label" 'use Illuminate\\' 'use Illuminate\'
}

check_dir "app/Support" "app/Support"

if [[ "$violations" -gt 0 ]]; then
    echo ""
    echo "Support layer isolation check failed: ${violations} violation(s)."
    echo "Keep app/Support free of Illuminate/Facades/helpers; move adapters to Http/ or Infrastructure/Laravel."
    exit 1
fi

echo "Support layer isolation check passed."
