# Production image: builds frontend assets, installs PHP deps, then serves
# the app with php-fpm + nginx supervised in a single container.

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

# The Wayfinder Vite plugin shells out to `php artisan wayfinder:generate`
# during the build, so this stage needs PHP + vendor + the full app source
# (for package discovery), not just Node.
FROM node:22-bookworm-slim AS frontend
RUN apt-get update && apt-get install -y --no-install-recommends \
        php-cli php-mbstring php-xml php-curl php-zip \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN php artisan package:discover --ansi
RUN npm run build

FROM php:8.3-fpm-bookworm AS app

# Runtime libs are installed in their own explicit step so apt keeps them
# marked "manually installed" — otherwise `apt-get purge --auto-remove` below
# (which drops the -dev/build packages) treats them as orphaned and removes
# the .so files the extensions link against at runtime.
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx supervisor unzip \
        libpq5 libzip4 libpng16-16 libjpeg62-turbo libfreetype6 libicu72 libonig5 \
    && apt-get install -y --no-install-recommends \
        libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql mbstring bcmath zip gd intl exif pcntl opcache \
    && apt-get purge -y --auto-remove libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev libonig-dev \
    && rm -rf /var/lib/apt/lists/*

COPY docker/app/opcache.ini /usr/local/etc/php/conf.d/opcache-prod.ini
COPY docker/app/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/app/nginx-http.conf /etc/nginx/available/nginx-http.conf
COPY docker/app/nginx-ssl.conf /etc/nginx/available/nginx-ssl.conf
COPY docker/app/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN rm -f /etc/nginx/sites-enabled/default && chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && mkdir -p /run/php

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
