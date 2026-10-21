FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libzip-dev libsqlite3-dev unzip \
    && docker-php-ext-install -j"$(nproc)" intl pdo_sqlite zip opcache \
    && rm -rf /var/lib/apt/lists/*
RUN printf '%s\n' \
    'expose_php = Off' \
    'upload_max_filesize = 100M' \
    'post_max_size = 120M' \
    > /usr/local/etc/php/conf.d/99-custom.ini


COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/development.ini

WORKDIR /var/www

EXPOSE 80

CMD ["php", "-S", "0.0.0.0:80", "-t", "public", "docker/router.php"]
