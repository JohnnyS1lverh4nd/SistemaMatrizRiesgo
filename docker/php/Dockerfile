# Imagen de producción de la API Laravel (PHP-FPM). La usan los servicios
# "app" (PHP-FPM) y "worker" (cola) de compose.yaml.
#
#   docker build -f docker/php/Dockerfile -t smr-api .

# --- Etapa 1: dependencias de Composer (sin las de desarrollo) ---------------
FROM composer:2.8 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
# Sin scripts: artisan aún no existe en esta etapa.
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# --- Etapa 2: imagen final ----------------------------------------------------
FROM php:8.3-fpm-bookworm

# Extensiones: pdo_mysql (MariaDB), gd e intl (PhpSpreadsheet), zip (xlsx),
# bcmath, opcache. Las librerías -dev solo se usan para compilar y se retiran.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libfreetype6 libjpeg62-turbo libpng16-16 libicu72 libzip4 \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libicu-dev libzip-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql gd intl zip bcmath opcache; \
    apt-get purge -y --auto-remove libfreetype6-dev libjpeg62-turbo-dev libpng-dev libicu-dev libzip-dev; \
    rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-produccion.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-produccion.conf
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint

WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app /var/www/html

# Solo storage/ y bootstrap/cache necesitan escritura.
RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/private \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
