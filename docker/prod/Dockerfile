FROM composer:2.8 AS vendor
WORKDIR /var/www/html

RUN apk add --no-cache icu-dev \
    && docker-php-ext-install -j"$(nproc)" intl

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --optimize-autoloader

FROM node:24-alpine AS frontend
WORKDIR /var/www/html

ARG VITE_REVERB_APP_KEY=""
ARG VITE_REVERB_HOST=""
ARG VITE_REVERB_PORT=""
ARG VITE_REVERB_SCHEME=""

ENV VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY}
ENV VITE_REVERB_HOST=${VITE_REVERB_HOST}
ENV VITE_REVERB_PORT=${VITE_REVERB_PORT}
ENV VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME}

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

FROM php:8.4-cli-alpine AS app
WORKDIR /var/www/html

RUN apk add --no-cache \
    bash \
    brotli-dev \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    libstdc++ \
    zlib-dev \
    linux-headers \
    $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && pecl install redis swoole \
    && docker-php-ext-enable redis swoole \
    && apk del --no-network $PHPIZE_DEPS

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
COPY --from=frontend /var/www/html/public/build ./public/build

RUN rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php bootstrap/cache/events.php bootstrap/cache/routes-v7.php \
    && php artisan package:discover --ansi \
    && php artisan config:clear --ansi

RUN rm -rf node_modules tests docker/8.0 docker/8.1 docker/8.2 docker/8.3 docker/8.4 docker/8.5 \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

USER www-data

EXPOSE ${APP_PORT:-8000} ${APP_PORT:-8000}

CMD ["sh", "-lc", "php artisan octane:start --server=swoole --host=0.0.0.0 --port=${APP_PORT:-8000} --workers=${OCTANE_WORKERS:-auto} --task-workers=${OCTANE_TASK_WORKERS:-auto} --max-requests=1000"]
