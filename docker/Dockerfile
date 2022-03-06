FROM php:latest

RUN apt-get update && \
    apt-get install -y \
        libzip-dev \
        zip \
  && docker-php-ext-install zip \
    && docker-php-ext-enable openssl
WORKDIR /var/www
EXPOSE 80
CMD [ "php", "-S", "0.0.0.0:80", "-t" ,"/var/www/public/" ]
