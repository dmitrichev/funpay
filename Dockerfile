FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    default-mysql-client \
    libmariadb-dev \
    && docker-php-ext-install mysqli

WORKDIR /var/www/html
