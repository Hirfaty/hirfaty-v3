#!/usr/bin/env bash
set -e

wait_for_db() {
    local host="${DB_HOST:-db}"
    local port="${DB_PORT:-5432}"
    local tries=0

    echo "Waiting for database at ${host}:${port}..."
    until (echo > "/dev/tcp/${host}/${port}") >/dev/null 2>&1; do
        tries=$((tries + 1))
        if [ "$tries" -ge 60 ]; then
            echo "Database never became reachable, giving up." >&2
            exit 1
        fi
        sleep 1
    done
    echo "Database is reachable."
}

# Domain routing, TLS and cert lifecycle now live in the shared proxy
# (docker/proxy/), so there is no vhost to select here — the single
# app.conf is baked into the image at build time.

wait_for_db
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link || true

exec "$@"
