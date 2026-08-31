# Shared edge proxy. Owns 80/443 on the host and is the only publicly bound
# container: it terminates TLS, serves ACME challenges, routes by Host header
# to the prod and staging app stacks, and gates staging behind basic auth.
#
# nginx 1.27 (not Debian's 1.22 that the app image ships) so the modern
# `http2 on;` directive is available.

FROM nginx:1.27-alpine

# Vhost fragments live outside conf.d because entrypoint.sh picks the -http or
# -ssl variant per domain at boot; only the selected copies land in conf.d,
# which is what nginx auto-includes. proxy-headers.conf is deliberately kept
# out of conf.d too — it is `include`d from inside location blocks, and would
# otherwise also be loaded globally.
COPY docker/proxy/vhosts/ /etc/nginx/available/
COPY docker/proxy/proxy-headers.conf /etc/nginx/proxy-headers.conf
COPY docker/proxy/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && rm -f /etc/nginx/conf.d/default.conf

EXPOSE 80 443

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]
