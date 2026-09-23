FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libzip-dev libsqlite3-dev unzip \
    && docker-php-ext-install -j"$(nproc)" intl pdo_sqlite zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/development.ini

WORKDIR /var/www
EXPOSE 80
CMD ["php", "-S", "0.0.0.0:80", "-t", "public", "docker/router.php"]
