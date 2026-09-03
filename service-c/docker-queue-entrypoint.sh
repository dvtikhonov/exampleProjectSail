#!/bin/sh
set -e

. /usr/local/bin/docker-init-storage.sh

# queue:work без docker-entrypoint.sh иначе создаёт max_log-* от root → FPM не может дописывать.
if command -v runuser >/dev/null 2>&1; then
    exec runuser -u www-data -- php artisan "$@"
elif command -v su >/dev/null 2>&1; then
    exec su -s /bin/sh www-data -c "php artisan $(printf '%q ' "$@")"
else
    echo "warning: cannot drop privileges for queue:work; running as current user" >&2
    exec php artisan "$@"
fi
