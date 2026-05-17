#!/bin/sh
# wait-for-it.sh host:port - ждёт, пока хост:порт не станет доступен
set -e
TIMEOUT=30
HOST=$(echo $1 | cut -d: -f1)
PORT=$(echo $1 | cut -d: -f2)
shift
cmd="$@"
until nc -z $HOST $PORT; do
  >&2 echo "Waiting for $HOST:$PORT..."
  sleep 1
  TIMEOUT=$((TIMEOUT - 1))
  if [ $TIMEOUT -le 0 ]; then
    >&2 echo "Timeout waiting for $HOST:$PORT"
    exit 1
  fi
done
exec $cmd
