# ─────────────────────────────────────────────
# Stage 1 – Composer dependencies
# ─────────────────────────────────────────────
FROM composer:2.8 AS vendor

WORKDIR /app

COPY composer.json composer.lock* ./

RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
        --ignore-platform-reqs

# ─────────────────────────────────────────────
# Stage 2 – Runtime image
# ─────────────────────────────────────────────
FROM php:8.2.35-apache

LABEL maintainer="Youth Employment Bridge"

# System packages
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        zip \
        unzip \
        libpng-dev \
        libjpeg-dev \
        libwebp-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_mysql \
        zip \
        gd \
        opcache \
    && apt-get purge -y --auto-remove && rm -rf /var/lib/apt/lists/*

# Apache: mod_rewrite + custom vhost
RUN a2enmod rewrite

COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

# PHP settings
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

# Application code
WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .

# Storage directories & permissions
RUN mkdir -p \
        storage/avatars \
        storage/company-avatars \
        storage/cvs \
        storage/id-scans \
        storage/logs \
        storage/cache \
    && chown -R www-data:www-data storage \
    && chmod -R 775 storage

EXPOSE 80
