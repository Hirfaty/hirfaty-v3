#!/bin/sh
set -e

# Each domain has its own certificate lifecycle, so each is selected
# independently between its -http (pre-cert) and -ssl (post-cert) fragment.
# Same pattern the app container used before TLS moved out here.
#
# `source_base` and `output_name` are separate so prod can point at either
# the prod-app-* fragments (real app) or prod-coming-soon-* fragments
# (placeholder) while always landing at conf.d/prod.conf either way — see
# PROD_MODE below.
select_site() {
    domain="$1"
    source_base="$2"
    output_name="$3"

    if [ -f "/etc/letsencrypt/live/${domain}/fullchain.pem" ]; then
        echo "[proxy] ${domain}: certificate found, serving HTTPS (${source_base})."
        cp "/etc/nginx/available/${source_base}-ssl.conf" "/etc/nginx/conf.d/${output_name}.conf"
    else
        echo "[proxy] ${domain}: no certificate yet, serving HTTP only (${source_base})."
        cp "/etc/nginx/available/${source_base}-http.conf" "/etc/nginx/conf.d/${output_name}.conf"
    fi
}

# PROD_MODE toggles hirfaty.com between the static placeholder and the real
# app with zero file changes — just set PROD_MODE=app in docker-compose.proxy.yml
# (or the shell environment) and `make proxy-build && make proxy-up` again
# when it's time for the real launch.
PROD_MODE="${PROD_MODE:-coming-soon}"
case "$PROD_MODE" in
    app) prod_source="prod-app" ;;
    coming-soon) prod_source="prod-coming-soon" ;;
    *)
        echo "[proxy] ERROR: PROD_MODE='${PROD_MODE}' is not 'app' or 'coming-soon'." >&2
        exit 1
        ;;
esac
echo "[proxy] PROD_MODE=${PROD_MODE} — hirfaty.com serving ${prod_source}."

# A missing htpasswd would let nginx start and then 500 on every staging
# request, which looks like an app fault. Fail loudly at boot instead.
if [ ! -s /etc/nginx/htpasswd ]; then
    echo "[proxy] ERROR: /etc/nginx/htpasswd is missing or empty." >&2
    echo "[proxy] Staging basic auth cannot work. Create it first:" >&2
    echo "[proxy]   make proxy-htpasswd USER=<user> PASS=<password>" >&2
    exit 1
fi

rm -f /etc/nginx/conf.d/*.conf

select_site "${PROD_DOMAIN:-hirfaty.com}" "$prod_source" prod
select_site "${STAGING_DOMAIN:-init.hirfaty.com}" staging staging

nginx -t

exec "$@"
