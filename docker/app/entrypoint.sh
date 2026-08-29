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

select_nginx_vhost() {
    local domain="${APP_DOMAIN:-hirfaty.com}"

    if [ -f "/etc/letsencrypt/live/${domain}/fullchain.pem" ]; then
        cp /etc/nginx/available/nginx-ssl.conf /etc/nginx/sites-enabled/default
    else
        cp /etc/nginx/available/nginx-http.conf /etc/nginx/sites-enabled/default
    fi
}

wait_for_db
php artisan config:cache
php artisan route:cache
php artisan view:cache
select_nginx_vhost

exec "$@"
