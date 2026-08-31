#!/bin/sh
set -e

# Each domain has its own certificate lifecycle, so each is selected
# independently between its -http (pre-cert) and -ssl (post-cert) fragment.
# Same pattern the app container used before TLS moved out here.
select_site() {
    domain="$1"
    site="$2"

    if [ -f "/etc/letsencrypt/live/${domain}/fullchain.pem" ]; then
        echo "[proxy] ${domain}: certificate found, serving HTTPS."
        cp "/etc/nginx/available/${site}-ssl.conf" "/etc/nginx/conf.d/${site}.conf"
    else
        echo "[proxy] ${domain}: no certificate yet, serving HTTP only."
        cp "/etc/nginx/available/${site}-http.conf" "/etc/nginx/conf.d/${site}.conf"
    fi
}

# A missing htpasswd would let nginx start and then 500 on every staging
# request, which looks like an app fault. Fail loudly at boot instead.
if [ ! -s /etc/nginx/htpasswd ]; then
    echo "[proxy] ERROR: /etc/nginx/htpasswd is missing or empty." >&2
    echo "[proxy] Staging basic auth cannot work. Create it first:" >&2
    echo "[proxy]   make proxy-htpasswd USER=<user> PASS=<password>" >&2
    exit 1
fi

rm -f /etc/nginx/conf.d/*.conf

select_site "${PROD_DOMAIN:-hirfaty.com}" prod
select_site "${STAGING_DOMAIN:-init.hirfaty.com}" staging

nginx -t

exec "$@"
