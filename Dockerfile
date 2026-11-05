FROM node:22-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json .npmrc vite.config.js ./
COPY resources ./resources
RUN npm ci && npm run build

FROM dunglas/frankenphp:1-php8.4-bookworm AS app
RUN install-php-extensions bcmath exif gd intl opcache pcntl pdo_pgsql pgsql zip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
ENV SERVER_NAME=:8080

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=assets /build/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev --no-scripts --no-interaction \
    && php artisan package:discover \
    && php artisan filament:assets \
    && chmod -R a+rX /app \
    && chown -R www-data:www-data storage bootstrap/cache /data/caddy /config/caddy

USER www-data
ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
