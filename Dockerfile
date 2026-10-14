# syntax=docker/dockerfile:1

# ---------- Base : PHP 8.3 + Apache + extensions requises ----------
FROM php:8.3-apache AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev libpq-dev libpng-dev libjpeg-dev libfreetype6-dev libzip-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql gd zip opcache \
    && a2enmod headers \
    && a2dissite 000-default \
    && rm -rf /var/lib/apt/lists/*

# ---------- Build : dépendances PHP, assets, cache ----------
FROM base AS build

RUN apt-get update && apt-get install -y --no-install-recommends git unzip && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
ENV APP_ENV=prod COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction

COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative \
    # Valeurs factices : seulement pour construire le cache. Les vraies valeurs sont lues à l'exécution.
    && APP_SECRET=build DATABASE_URL="postgresql://u:p@localhost/db?serverVersion=16" \
        sh -c 'php bin/console asset-map:compile && php bin/console cache:warmup' \
    && chown -R www-data:www-data var

# ---------- Image finale ----------
FROM base

ENV APP_ENV=prod APP_DEBUG=0 PORT=80
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/apache.conf /etc/apache2/sites-available/app.conf
RUN : > /etc/apache2/ports.conf && a2ensite app

WORKDIR /var/www/html
COPY --from=build /app .
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
