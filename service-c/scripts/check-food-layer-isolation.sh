#!/usr/bin/env bash
# Fail if Eloquent models leak into Food contracts or services.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

violations=0

check_dir() {
    local dir="$1"
    local label="$2"

    if [[ ! -d "$dir" ]]; then
        return
    fi

    while IFS= read -r file; do
        [[ -z "$file" ]] && continue
        echo "ERROR: App\\Models in ${label}: ${file#${ROOT}/}"
        violations=$((violations + 1))
    done < <(grep -rl 'App\\Models' "$dir" --include='*.php' 2>/dev/null || true)
}

check_dir "app/Contracts/Food" "app/Contracts/Food"
check_dir "app/Services/Food" "app/Services/Food"

if [[ "$violations" -gt 0 ]]; then
    echo ""
    echo "Food layer isolation check failed: ${violations} violation(s)."
    echo "Move Eloquent to app/Repositories/Food/ and map to *Record/*Dto at boundaries."
    exit 1
fi

echo "Food layer isolation check passed."
