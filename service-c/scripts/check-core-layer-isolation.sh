#!/usr/bin/env bash
# Fail if Services/Contracts gain new Illuminate\ / App\Models\ leaks beyond Phase 0 baseline.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
exec bash "${ROOT}/scripts/architecture-leak-inventory.sh" --check
