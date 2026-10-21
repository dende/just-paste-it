FROM php:8.4-cli-alpine

RUN apk add --no-cache icu-libs libzip sqlite-libs unzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS icu-dev libzip-dev sqlite-dev \
    && docker-php-ext-install -j"$(nproc)" intl pdo_sqlite zip opcache \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/development.ini

WORKDIR /var/www

COPY . .
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --prefer-dist --no-progress --no-scripts

EXPOSE 80

CMD ["php", "-S", "0.0.0.0:80", "-t", "public", "docker/router.php"]
