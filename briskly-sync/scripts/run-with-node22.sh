#!/usr/bin/env bash
# Запуск команды под Node >= 22.13 (nvm), иначе — текущий node с предупреждением.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

NVM_SH="${NVM_DIR:-$HOME/.nvm}/nvm.sh"
if [[ -s "$NVM_SH" ]]; then
  # shellcheck disable=SC1090
  . "$NVM_SH"
  nvm use 22 >/dev/null 2>&1 || true
fi

NODE_MAJOR="$(node -p "process.versions.node.split('.')[0]")"
NODE_MINOR="$(node -p "process.versions.node.split('.')[1]")"
if [[ "$NODE_MAJOR" -lt 22 ]] || { [[ "$NODE_MAJOR" -eq 22 ]] && [[ "$NODE_MINOR" -lt 13 ]]; }; then
  echo "warn: briskly-sync prefers Node >= 22.13 (current: $(node -v)); Cursor SDK may use Jsonl store fallback" >&2
fi

exec "$@"
