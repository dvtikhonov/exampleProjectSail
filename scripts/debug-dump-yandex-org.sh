#!/usr/bin/env bash
set -euo pipefail

ORG_ID="${1:-115272305870}"
CANONICAL_URL="${2:-https://yandex.ru/maps/org/invitro/${ORG_ID}/}"
PARSER_URL="${YANDEX_PARSER_URL:-http://yandex-parser:3000}"

echo "Triggering sync-reviews for org_id=${ORG_ID}"
echo "canonical_url=${CANONICAL_URL}"
echo "parser=${PARSER_URL} (via service-d container)"
echo "Dump will appear in yandex-parser/debug-dumps/ and debug-029acb.log"

docker compose exec -T service-d curl -sS -X POST "${PARSER_URL}/sync-reviews" \
  -H 'Content-Type: application/json' \
  -d "{\"org_id\":\"${ORG_ID}\",\"canonical_url\":\"${CANONICAL_URL}\"}" \
  | tee "/tmp/yandex-sync-${ORG_ID}.json"

echo
echo "Done. Check:"
echo "  - yandex-parser/debug-dumps/org-${ORG_ID}-*.ndjson"
echo "  - debug-029acb.log"
