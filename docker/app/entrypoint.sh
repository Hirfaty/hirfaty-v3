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

# The /media/ proxy in app.conf must read from the SeaweedFS the app writes to.
# The image's default upstream is the bare service name `seaweedfs`, but on the
# shared `edge` network that name resolves to *every* stack's SeaweedFS (each
# joins edge, and compose aliases every service by name) — so prod's /media/
# could be served from staging's storage and answer NoSuchKey for a file prod
# just wrote. SEAWEEDFS_ENDPOINT already names the right container for the
# writes (e.g. http://hirfaty-seaweedfs:8333), so reuse its host for the reads.
if [ -n "${SEAWEEDFS_ENDPOINT:-}" ]; then
    seaweedfs_host="${SEAWEEDFS_ENDPOINT#*://}"
    seaweedfs_host="${seaweedfs_host%%[:/]*}"
    sed -i "s|set \$seaweedfs_upstream .*;|set \$seaweedfs_upstream ${seaweedfs_host};|" /etc/nginx/sites-enabled/app
fi

wait_for_db
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link || true

exec "$@"
