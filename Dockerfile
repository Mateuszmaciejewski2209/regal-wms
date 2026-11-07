# syntax=docker/dockerfile:1.7
#
# Regał WMS – obraz wieloetapowy:
#   assets  → build front-endu (Vite + Tailwind)
#   vendor  → zależności Composera (bez dev)
#   app     → PHP-FPM z aplikacją (używany też przez Reverb, kolejkę i migracje)
#   web     → nginx z plikami publicznymi (proxy PHP i WebSocket)
#   test    → PHPUnit z zależnościami dev (tylko do testów, nie trafia do środowiska)

########################################
# 1. Front-end
########################################
FROM node:22-alpine AS assets
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
RUN mkdir -p public

# Puste HOST/PORT/SCHEME = WebSocket na tym samym adresie co strona (nginx przekazuje /app do Reverba).
ARG VITE_APP_NAME="Regał WMS"
ARG VITE_REVERB_APP_KEY=regal-key
ARG VITE_REVERB_HOST=""
ARG VITE_REVERB_PORT=""
ARG VITE_REVERB_SCHEME=""
ENV VITE_APP_NAME=$VITE_APP_NAME \
    VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

RUN npm run build

########################################
# 2. PHP z rozszerzeniami
########################################
FROM php:8.4-fpm-alpine AS php-base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql bcmath intl gd zip pcntl opcache
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/regal.ini /usr/local/etc/php/conf.d/zz-regal.ini

WORKDIR /var/www/html

########################################
# 3. Zależności PHP (warstwa cache – przebudowa tylko po zmianie composer.lock)
########################################
FROM php-base AS vendor
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

########################################
# 4. Aplikacja (PHP-FPM / artisan)
########################################
FROM php-base AS app

COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /var/www/html/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && composer dump-autoload --optimize --no-dev --no-interaction \
 && chown -R www-data:www-data storage bootstrap/cache

COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/regal-entrypoint

USER www-data
EXPOSE 9000
ENTRYPOINT ["regal-entrypoint"]
CMD ["php-fpm"]

########################################
# 5. Serwer WWW (nginx)
########################################
FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public

########################################
# 6. Testy (PHPUnit z zależnościami dev)
#    docker compose --profile test run --rm tests      → SQLite w pamięci
#    docker compose --profile test run --rm tests-pg   → PostgreSQL (baza regal_test)
########################################
FROM php-base AS test
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
COPY --from=assets /app/public/build ./public/build

# Pusty .env: konfiguracja testów pochodzi z phpunit.xml i zmiennych środowiska usługi.
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && touch .env \
 && composer dump-autoload --no-interaction

CMD ["php", "artisan", "test"]
