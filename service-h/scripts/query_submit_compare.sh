#!/usr/bin/env bash
# Сводка InfluxDB: food_submit wall-clock vs Server-Timing (последние 3 часа).
# Запуск из WSL при поднятом service-h-influxdb (порт 8089).
set -euo pipefail

INFLUX_URL="${INFLUX_URL:-http://localhost:8089}"
DB="${INFLUX_DB:-k6}"

echo "=== measurements food_submit* ==="
curl -sG "${INFLUX_URL}/query" \
  --data-urlencode "db=${DB}" \
  --data-urlencode 'q=SHOW MEASUREMENTS' | tr ',' '\n' | grep -E 'food_submit' || true

echo
echo "=== last 3h: mean / p95 / p99 / max / count ==="
curl -sG "${INFLUX_URL}/query" \
  --data-urlencode "db=${DB}" \
  --data-urlencode 'pretty=true' \
  --data-urlencode 'q=SELECT mean("value") AS mean_ms, percentile("value", 95) AS p95_ms, percentile("value", 99) AS p99_ms, max("value") AS max_ms, count("value") AS n FROM "food_submit_duration","food_submit_t_tx","food_submit_t_notify","food_submit_t_submit" WHERE time > now() - 3h'

echo
echo "=== last 3h: 5m mean wall vs t_submit (sample) ==="
curl -sG "${INFLUX_URL}/query" \
  --data-urlencode "db=${DB}" \
  --data-urlencode 'pretty=true' \
  --data-urlencode 'q=SELECT mean("value") FROM "food_submit_duration","food_submit_t_submit" WHERE time > now() - 3h GROUP BY time(5m) fill(none) LIMIT 30'

echo
