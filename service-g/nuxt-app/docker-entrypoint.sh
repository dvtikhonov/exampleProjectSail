#!/bin/sh
set -e

cd /app

if [ ! -f node_modules/nitropack/dist/runtime/index.mjs ]; then
    echo "node_modules incomplete — running npm ci..."
    npm ci
fi

exec npm run dev -- --host 0.0.0.0 --port 3000
