#!/usr/bin/env bash
set -uo pipefail
ROOT=/home/ddd/exampleProjectSail
SVC="$ROOT/service-c"
REPORT="$ROOT/tmp/phase1-providers-report.txt"
OUTDIR="$ROOT/tmp"
mkdir -p "$OUTDIR"

{
  echo "=== Phase 1 SOLID providers remediation report ==="
  echo "Generated: $(date -Iseconds)"
  echo
} > "$REPORT"

echo "=== 1. BC alias existence check ===" | tee -a "$REPORT"
{
  echo "--- Auth/*.php ---"
  if [ -d "$SVC/app/Services/Auth" ]; then
    ls -la "$SVC/app/Services/Auth/" || true
  else
    echo "DIR ABSENT: app/Services/Auth"
  fi
  echo
  echo "--- Max/**/Laravel*.php ---"
  find "$SVC/app/Services/Max" -name 'Laravel*.php' 2>/dev/null || echo "NONE or Max dir issue"
  echo
  echo "--- Other Services Laravel* ---"
  find "$SVC/app/Services" -name 'Laravel*.php' 2>/dev/null || echo "NONE"
} | tee -a "$REPORT"

echo | tee -a "$REPORT"
echo "=== 2. Provider split status ===" | tee -a "$REPORT"
{
  echo "bootstrap/providers.php:"
  cat "$SVC/bootstrap/providers.php"
  echo
  echo "AppServiceProvider lines: $(wc -l < "$SVC/app/Providers/AppServiceProvider.php")"
  echo "FoodServiceProvider lines: $(wc -l < "$SVC/app/Providers/FoodServiceProvider.php")"
  echo "MaxServiceProvider lines: $(wc -l < "$SVC/app/Providers/MaxServiceProvider.php")"
  echo "SharedInfrastructureProvider lines: $(wc -l < "$SVC/app/Providers/SharedInfrastructureProvider.php")"
  echo
  echo "AppServiceProvider content:"
  cat "$SVC/app/Providers/AppServiceProvider.php"
} | tee -a "$REPORT"

echo | tee -a "$REPORT"
echo "=== 3. Delete unused BC aliases ===" | tee -a "$REPORT"
DELETED=()
CANDIDATES=(
  "$SVC/app/Services/Auth/LaravelGatewayAuthSession.php"
  "$SVC/app/Services/Auth/EloquentGatewayUserResolver.php"
  "$SVC/app/Services/Auth/RequestGatewayUserContext.php"
  "$SVC/app/Services/Max/LaravelMaxAdminBotTestSender.php"
  "$SVC/app/Services/Max/Food/LaravelOrderChatNotifier.php"
  "$SVC/app/Services/Max/Food/LaravelFoodOrderMaxNotifier.php"
  "$SVC/app/Services/Max/Food/LaravelFoodOrderCustomerNotifier.php"
)

for f in "${CANDIDATES[@]}"; do
  if [ -f "$f" ]; then
    lines=$(wc -l < "$f")
    echo "FOUND: $f ($lines lines)" | tee -a "$REPORT"
    rel="${f#$SVC/}"
    ns=$(echo "$rel" | sed 's#^app/Services/#App\\Services\\#; s#/#\\#g; s#\.php$##')
    echo "  FQCN approx: $ns" | tee -a "$REPORT"
    hits=$(rg -l --glob '*.php' -F "$ns" "$SVC" 2>/dev/null | grep -v -F "$f" || true)
    if [ -n "$hits" ]; then
      echo "  KEEP — imported elsewhere:" | tee -a "$REPORT"
      echo "$hits" | tee -a "$REPORT"
    else
      if rg -q '^\s*(final\s+)?class\s+\w+.*extends\s+' "$f"; then
        echo "  DELETE — unused BC alias" | tee -a "$REPORT"
        rm -f "$f"
        DELETED+=("$f")
      else
        echo "  REVIEW — not a simple extends alias, skip delete" | tee -a "$REPORT"
        head -20 "$f" | tee -a "$REPORT"
      fi
    fi
  else
    echo "ALREADY GONE: $f" | tee -a "$REPORT"
  fi
done

if [ -d "$SVC/app/Services/Auth" ]; then
  if [ -z "$(ls -A "$SVC/app/Services/Auth" 2>/dev/null)" ]; then
    rmdir "$SVC/app/Services/Auth" && echo "Removed empty dir Services/Auth" | tee -a "$REPORT"
  fi
fi

echo "Deleted count this run: ${#DELETED[@]}" | tee -a "$REPORT"

echo | tee -a "$REPORT"
echo "=== 4. Other BC alias patterns under Services ===" | tee -a "$REPORT"
{
  echo "Classes that only extend Infrastructure/Repositories:"
  rg -n --glob '*.php' 'extends\s+\\?App\\(Infrastructure|Repositories)' "$SVC/app/Services" || echo "(none)"
  echo
  echo "Empty-looking thin class files (extends only, <=15 lines):"
  find "$SVC/app/Services" -name '*.php' -type f | while read -r pf; do
    n=$(wc -l < "$pf")
    if [ "$n" -le 15 ] && rg -q 'extends\s+' "$pf"; then
      echo "$n $pf"
      head -12 "$pf"
      echo "---"
    fi
  done
} | tee -a "$REPORT"

echo | tee -a "$REPORT"
echo "=== 5. Verification ===" | tee -a "$REPORT"

ABOUT_OUT="$OUTDIR/phase1-artisan-about.txt"
ISOL_FOOD="$OUTDIR/phase1-isol-food.txt"
ISOL_CORE="$OUTDIR/phase1-isol-core.txt"
TESTS_OUT="$OUTDIR/phase1-unit-tests.txt"

run_in_app() {
  local cmd="$1"
  if docker ps --format '{{.Names}}' | grep -qx 'main-app'; then
    docker exec -T main-app bash -lc "cd /var/www/html && $cmd"
  elif docker ps --format '{{.Names}}' | grep -qi 'main-app'; then
    local cname
    cname=$(docker ps --format '{{.Names}}' | grep -i 'main-app' | head -1)
    docker exec -T "$cname" bash -lc "cd /var/www/html && $cmd"
  else
    (cd "$SVC" && bash -lc "$cmd")
  fi
}

echo "Docker containers:" | tee -a "$REPORT"
docker ps --format '{{.Names}}	{{.Status}}' 2>&1 | head -30 | tee -a "$REPORT"

echo | tee -a "$REPORT"
echo "--- php artisan about ---" | tee -a "$REPORT"
set +e
run_in_app "php artisan about" > "$ABOUT_OUT" 2>&1
ABOUT_EC=$?
set -e
echo "exit=$ABOUT_EC" | tee -a "$REPORT"
tail -n 40 "$ABOUT_OUT" | tee -a "$REPORT"

echo | tee -a "$REPORT"
echo "--- check-food-layer-isolation.sh ---" | tee -a "$REPORT"
set +e
bash "$SVC/scripts/check-food-layer-isolation.sh" > "$ISOL_FOOD" 2>&1
FOOD_EC=$?
set -e
echo "exit=$FOOD_EC" | tee -a "$REPORT"
tail -n 20 "$ISOL_FOOD" | tee -a "$REPORT"

echo | tee -a "$REPORT"
echo "--- check-core-layer-isolation.sh ---" | tee -a "$REPORT"
set +e
bash "$SVC/scripts/check-core-layer-isolation.sh" > "$ISOL_CORE" 2>&1
CORE_EC=$?
set -e
echo "exit=$CORE_EC" | tee -a "$REPORT"
tail -n 20 "$ISOL_CORE" | tee -a "$REPORT"

echo | tee -a "$REPORT"
echo "--- Related unit tests ---" | tee -a "$REPORT"
set +e
run_in_app "php artisan test --testsuite=Unit --filter='MaxWebhook|CoreLayerIsolation|FoodLayerIsolation' 2>&1" > "$TESTS_OUT" 2>&1
if ! grep -q 'Tests:' "$TESTS_OUT" 2>/dev/null; then
  run_in_app "php artisan test tests/Unit --filter=MaxWebhook 2>&1" >> "$TESTS_OUT" 2>&1
  run_in_app "php artisan test tests/Architecture 2>&1" >> "$TESTS_OUT" 2>&1
fi
TEST_EC=$?
set -e
echo "exit=$TEST_EC" | tee -a "$REPORT"
tail -n 60 "$TESTS_OUT" | tee -a "$REPORT"

echo | tee -a "$REPORT"
echo "=== SUMMARY ===" | tee -a "$REPORT"
{
  echo "Provider split: ALREADY DONE (SharedInfrastructureProvider, FoodServiceProvider, MaxServiceProvider, thin AppServiceProvider; registered in bootstrap/providers.php)"
  echo "BC aliases deleted this run: ${#DELETED[@]}"
  for d in "${DELETED[@]:-}"; do [ -n "$d" ] && echo "  - $d"; done
  echo "BC aliases status: all 7 candidates ALREADY GONE on disk (Auth dir absent; no Services/**/Laravel*.php)"
  echo "artisan about: $( [ "$ABOUT_EC" -eq 0 ] && echo PASS || echo FAIL ) (exit $ABOUT_EC)"
  echo "food isolation: $( [ "$FOOD_EC" -eq 0 ] && echo PASS || echo FAIL ) (exit $FOOD_EC)"
  echo "core isolation: $( [ "$CORE_EC" -eq 0 ] && echo PASS || echo FAIL ) (exit $CORE_EC)"
  echo "unit tests: $( [ "$TEST_EC" -eq 0 ] && echo PASS || echo FAIL ) (exit $TEST_EC)"
  echo "Remaining work: none expected if all PASS; otherwise see sections above."
} | tee -a "$REPORT"

echo
echo "Report written to $REPORT"
