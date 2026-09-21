#!/usr/bin/env bash
# Fail if Eloquent models, Laravel helpers/facades, or Illuminate imports
# leak into Food contracts or services.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

violations=0

# Patterns forbidden in Food application layer (Contracts/Services).
# - App\Models (Eloquent)
# - config('…') / event(…) helpers
# - DB:: / Log:: / Storage:: / Cache:: facades
# - use Illuminate\…
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
    # String-key form of Laravel config() helper; avoids method names like ->config()
    check_pattern "$dir" "$label" 'config[[:space:]]*\([[:space:]]*['\''"]' 'config() helper'
    check_pattern "$dir" "$label" '\bevent[[:space:]]*\(' 'event() helper'
    check_pattern "$dir" "$label" '\bDB::' 'DB:: facade'
    check_pattern "$dir" "$label" '\bLog::' 'Log:: facade'
    check_pattern "$dir" "$label" '\bStorage::' 'Storage:: facade'
    check_pattern "$dir" "$label" '\bCache::' 'Cache:: facade'
    check_pattern "$dir" "$label" 'use Illuminate\\' 'use Illuminate\'
    # Food core must not import module implementations (DIP / dependency inversion).
    check_pattern "$dir" "$label" 'App\\Modules\\' 'App\Modules\'
}

check_dir "app/Contracts/Food" "app/Contracts/Food"
check_dir "app/Services/Food" "app/Services/Food"

if [[ "$violations" -gt 0 ]]; then
    echo ""
    echo "Food layer isolation check failed: ${violations} violation(s)."
    echo "Move Eloquent/Facades/helpers out of Food Contracts/Services; use ports (*Interface) and Repositories/Infrastructure."
    exit 1
fi

echo "Food layer isolation check passed."
