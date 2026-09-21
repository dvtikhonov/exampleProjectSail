#!/usr/bin/env bash
# Fail if core layers (Services/Contracts/DTO/Enums/Exceptions) gain new
# Illuminate\ / App\Models\ / helper+facade leaks beyond Phase 0 baseline
# (config/event helpers, DB/Log/Storage/Cache facades).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
exec bash "${ROOT}/scripts/architecture-leak-inventory.sh" --check
