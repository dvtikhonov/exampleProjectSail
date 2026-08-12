#!/bin/sh
set -e

# Defaults match service-c/php-fpm.conf (safe for prod mem_limit).
FPM_CONF="/usr/local/etc/php-fpm.d/zzz-www.conf"
FPM_DEFAULT_MAX_CHILDREN=64
FPM_DEFAULT_START_SERVERS=8
FPM_DEFAULT_MIN_SPARE=4
FPM_DEFAULT_MAX_SPARE=16
FPM_DEFAULT_LISTEN_BACKLOG=1024
FPM_DEFAULT_MAX_REQUESTS=500

# Returns 0 if $1 is a positive integer (>= 1).
fpm_is_positive_int() {
    case "$1" in
        ''|*[!0-9]*) return 1 ;;
        0) return 1 ;;
        *) return 0 ;;
    esac
}

# Apply PHP_FPM_* env to zzz-www.conf; invalid values → defaults + stderr warning.
apply_php_fpm_pool_settings() {
    if [ ! -f "$FPM_CONF" ]; then
        echo "warning: FPM pool config not found at $FPM_CONF, skipping PHP_FPM_* overlay" >&2
        return 0
    fi

    max_children="${PHP_FPM_MAX_CHILDREN:-$FPM_DEFAULT_MAX_CHILDREN}"
    start_servers="${PHP_FPM_START_SERVERS:-$FPM_DEFAULT_START_SERVERS}"
    min_spare="${PHP_FPM_MIN_SPARE_SERVERS:-$FPM_DEFAULT_MIN_SPARE}"
    max_spare="${PHP_FPM_MAX_SPARE_SERVERS:-$FPM_DEFAULT_MAX_SPARE}"
    listen_backlog="${PHP_FPM_LISTEN_BACKLOG:-$FPM_DEFAULT_LISTEN_BACKLOG}"
    max_requests="${PHP_FPM_MAX_REQUESTS:-$FPM_DEFAULT_MAX_REQUESTS}"

    valid=1
    if ! fpm_is_positive_int "$max_children"; then
        valid=0
    fi
    if ! fpm_is_positive_int "$start_servers"; then
        valid=0
    fi
    if ! fpm_is_positive_int "$min_spare"; then
        valid=0
    fi
    if ! fpm_is_positive_int "$max_spare"; then
        valid=0
    fi
    if ! fpm_is_positive_int "$listen_backlog"; then
        valid=0
    fi
    if ! fpm_is_positive_int "$max_requests"; then
        valid=0
    fi

    if [ "$valid" -eq 1 ]; then
        # min_spare ≤ max_spare ≤ max_children; start_servers within [min_spare, max_spare]
        if [ "$min_spare" -gt "$max_spare" ] || [ "$max_spare" -gt "$max_children" ]; then
            valid=0
        elif [ "$start_servers" -lt "$min_spare" ] || [ "$start_servers" -gt "$max_spare" ]; then
            valid=0
        fi
    fi

    if [ "$valid" -eq 0 ]; then
        echo "warning: invalid PHP_FPM_* (need integers >=1 and min_spare <= start_servers <= max_spare <= max_children; listen.backlog/max_requests >=1); using defaults ${FPM_DEFAULT_MAX_CHILDREN}/${FPM_DEFAULT_START_SERVERS}/${FPM_DEFAULT_MIN_SPARE}/${FPM_DEFAULT_MAX_SPARE} backlog=${FPM_DEFAULT_LISTEN_BACKLOG} max_requests=${FPM_DEFAULT_MAX_REQUESTS}" >&2
        max_children="$FPM_DEFAULT_MAX_CHILDREN"
        start_servers="$FPM_DEFAULT_START_SERVERS"
        min_spare="$FPM_DEFAULT_MIN_SPARE"
        max_spare="$FPM_DEFAULT_MAX_SPARE"
        listen_backlog="$FPM_DEFAULT_LISTEN_BACKLOG"
        max_requests="$FPM_DEFAULT_MAX_REQUESTS"
    fi

    tmp_conf="${FPM_CONF}.tmp.$$"
    sed \
        -e "s/^listen\\.backlog = .*/listen.backlog = ${listen_backlog}/" \
        -e "s/^pm\\.max_children = .*/pm.max_children = ${max_children}/" \
        -e "s/^pm\\.start_servers = .*/pm.start_servers = ${start_servers}/" \
        -e "s/^pm\\.min_spare_servers = .*/pm.min_spare_servers = ${min_spare}/" \
        -e "s/^pm\\.max_spare_servers = .*/pm.max_spare_servers = ${max_spare}/" \
        -e "s/^pm\\.max_requests = .*/pm.max_requests = ${max_requests}/" \
        "$FPM_CONF" > "$tmp_conf"
    mv "$tmp_conf" "$FPM_CONF"

    # Older images may lack these keys; sed above is a no-op then — append once.
    if ! grep -qE '^listen\.backlog = ' "$FPM_CONF"; then
        echo "listen.backlog = ${listen_backlog}" >> "$FPM_CONF"
    fi
    if ! grep -qE '^pm\.max_requests = ' "$FPM_CONF"; then
        echo "pm.max_requests = ${max_requests}" >> "$FPM_CONF"
    fi

    echo "php-fpm pool: max_children=${max_children} start_servers=${start_servers} min_spare=${min_spare} max_spare=${max_spare} listen.backlog=${listen_backlog} max_requests=${max_requests}"
}

mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache

for log_file in storage/logs/laravel.log storage/logs/messMax.log; do
    touch "$log_file"
done

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Mini-app в MAX (web/desktop/mobile) через туннель: только production build.
# Vite dev (public/hot → localhost:5174) снаружи недоступен.
rm -f public/hot 2>/dev/null || true
if [ ! -f public/max-build/manifest.json ]; then
    echo "Building Vite assets for max-app (required for MAX via tunnel)..."
    npm run build
fi

apply_php_fpm_pool_settings

php artisan schedule:work > storage/logs/schedule-work.log 2>&1 &

exec "$@"
