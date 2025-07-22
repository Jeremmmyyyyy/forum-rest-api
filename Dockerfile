FROM php:8.2-cli

# Install dependencies for PHP extensions
RUN apt-get update && \
    apt-get install -y libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libxml2-dev libmcrypt-dev libzip-dev libssl-dev libc-client2007e-dev libkrb5-dev unzip && \
    docker-php-ext-configure imap --with-kerberos --with-imap-ssl && \
    docker-php-ext-install mysqli imap

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app