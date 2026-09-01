# Production image: builds frontend assets, installs PHP deps, then serves
# the app with php-fpm + nginx supervised in a single container.

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Only extension requirements are ignored, NOT the PHP version requirement.
# The blanket --ignore-platform-reqs used here previously also suppressed the
# php constraint, which let the Symfony 8 stack (18 packages, all `php: >=8.4`)
# install into what was then a PHP 8.3 runtime image. Nothing failed at build
# time; it blew up in production instead, on the first DELETE/PATCH/PUT request
# — Symfony's Request::createFromGlobals() calls PHP 8.4's request_parse_body()
# unguarded for those verbs. composer.json pins config.platform.php to the
# runtime version so this is validated against the image PHP below, not against
# whatever PHP the composer image happens to ship.
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-req=ext-*

# Shared PHP layer for every stage that runs PHP — the frontend build (which
# shells out to artisan) and the final runtime image both derive from it.
#
# PHP 8.4, not 8.3: composer.lock pins the Symfony 8 stack, every package of
# which requires `php: >=8.4`. Keep in sync with config.platform.php in
# composer.json — if they drift, the vendor stage above now fails the build
# rather than shipping a runtime that can't run its own dependencies.
#
# Having exactly one place that decides the PHP version is the point. The
# frontend stage used to be `FROM node:22-bookworm-slim` + Debian's php-cli,
# which is PHP 8.2 — a third, invisible PHP version that only surfaced once
# the platform check started being enforced.
FROM php:8.4-fpm-bookworm AS php-base

# Runtime libs are installed in their own explicit step so apt keeps them
# marked "manually installed" — otherwise `apt-get purge --auto-remove` below
# (which drops the -dev/build packages) treats them as orphaned and removes
# the .so files the extensions link against at runtime.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq5 libzip4 libpng16-16 libjpeg62-turbo libfreetype6 libicu72 libonig5 \
    && apt-get install -y --no-install-recommends \
        libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql mbstring bcmath zip gd intl exif pcntl opcache \
    && apt-get purge -y --auto-remove libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# The Wayfinder Vite plugin shells out to `php artisan wayfinder:generate`
# during `npm run build`, so this stage needs PHP + vendor + the full app
# source (for package discovery), not just Node. Node is copied in from the
# official image rather than the other way around, so the PHP here is by
# construction the same build as the runtime's.
FROM php-base AS frontend
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN php artisan package:discover --ansi
RUN npm run build

FROM php-base AS app

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx supervisor unzip \
    && rm -rf /var/lib/apt/lists/*

COPY docker/app/opcache.ini /usr/local/etc/php/conf.d/opcache-prod.ini
COPY docker/app/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/app/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/app/vhosts/app.conf /etc/nginx/sites-enabled/app
COPY docker/app/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
# nginx here comes from apt, not the official nginx image, so it keeps its
# default file-based logs at /var/log/nginx/*.log — invisible to `docker logs`,
# which makes upstream/502 errors effectively undebuggable from the host.
# Symlink both streams to the container's stdout/stderr the way the official
# nginx image does, so supervisord forwards them.
RUN rm -f /etc/nginx/sites-enabled/default \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log \
    && chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && mkdir -p /run/php

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
