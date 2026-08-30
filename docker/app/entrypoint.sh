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

# Each site (init.hirfaty.com, hirfaty.com) has its own cert lifecycle, so
# each is selected independently between its -http (pre-cert) and -ssl
# (post-cert) fragment, then dropped into sites-enabled under its own name.
select_nginx_vhost() {
    local domain="$1"
    local site="$2"

    if [ -f "/etc/letsencrypt/live/${domain}/fullchain.pem" ]; then
        cp "/etc/nginx/available/${site}-ssl.conf" "/etc/nginx/sites-enabled/${site}"
    else
        cp "/etc/nginx/available/${site}-http.conf" "/etc/nginx/sites-enabled/${site}"
    fi
}

wait_for_db
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link || true
select_nginx_vhost "${APP_DOMAIN:-init.hirfaty.com}" init
select_nginx_vhost hirfaty.com hirfaty

exec "$@"
