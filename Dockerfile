# syntax=docker/dockerfile:1.7

# ---------------------------------------------------------------------------
# Composer dependency stages
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor-development

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
        --no-interaction \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
    && composer dump-autoload --optimize --no-scripts

FROM composer:2 AS vendor-production

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
    && composer dump-autoload --no-dev --optimize --no-scripts

# ---------------------------------------------------------------------------
# Front-end asset stage
# ---------------------------------------------------------------------------
FROM node:22 AS assets

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci --ignore-scripts --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources

RUN npm run build

# ---------------------------------------------------------------------------
# Shared PHP 8.4 + Apache runtime
# ---------------------------------------------------------------------------
FROM php:8.4-apache AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        pdo_pgsql \
        pgsql \
        zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint

RUN chmod +x /usr/local/bin/docker-entrypoint

WORKDIR /var/www/html

EXPOSE 80

ENTRYPOINT ["docker-entrypoint"]
CMD ["apache2-foreground"]

# ---------------------------------------------------------------------------
# Local / CI image (dev dependencies, tests)
# ---------------------------------------------------------------------------
FROM base AS development

COPY --from=vendor-development /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

COPY . .

RUN mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# ---------------------------------------------------------------------------
# Production image (no dev dependencies)
# ---------------------------------------------------------------------------
FROM base AS production

ENV APP_ENV=production

COPY --from=vendor-production /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

COPY . .

RUN mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
