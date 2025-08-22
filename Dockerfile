FROM php:8.2-cli-bullseye

RUN apt-get update && \
    apt-get install -y libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libxml2-dev libmcrypt-dev libzip-dev libssl-dev libc-client-dev libkrb5-dev unzip && \
    docker-php-ext-configure imap --with-kerberos --with-imap-ssl && \
    docker-php-ext-install mysqli imap

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /app
