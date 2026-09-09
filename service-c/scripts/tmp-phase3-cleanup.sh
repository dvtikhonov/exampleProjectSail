#!/usr/bin/env bash
set -euo pipefail
cd /home/ddd/exampleProjectSail/service-c
echo "=== Support Max before ==="
ls -la app/Support/Max/ || true
echo "=== Support Http before ==="
ls -la app/Support/Http/ 2>/dev/null || echo "(no Http dir)"
# Remove relocated Illuminate Support files if still present
rm -f \
  app/Support/Http/QueryParamParser.php \
  app/Support/Max/MaxAppRequestContext.php \
  app/Support/Max/MaxLocalDevInitData.php \
  app/Support/Max/MaxUiStandRecipientRegistry.php \
  app/Support/Max/MaxUiStandRecipientResolver.php \
  app/Support/Max/MaxOpenAppTargetResolver.php \
  app/Support/Max/MaxOpenAppButtonFactory.php \
  app/Support/Max/MaxMiniAppAccessLogger.php
rmdir app/Support/Http 2>/dev/null || true
echo "=== Support Max after ==="
ls -la app/Support/Max/
echo "=== Illuminate in Support ==="
grep -rnE 'use Illuminate\\|config\s*\(\s*['\''"]|request\s*\(' app/Support --include='*.php' || echo "(none)"
echo "=== New locations ==="
ls app/Http/Support/
ls app/Infrastructure/Laravel/LaravelMaxUiStand*.php app/Infrastructure/Laravel/MaxOpenApp*.php
chmod +x scripts/check-support-layer-isolation.sh
bash scripts/check-support-layer-isolation.sh
bash scripts/check-food-layer-isolation.sh
bash scripts/check-core-layer-isolation.sh
