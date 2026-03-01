FROM php:8.4-cli-bookworm

RUN apt-get update && \
    apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libmcrypt-dev \
    libzip-dev \
    libssl-dev \
    libc-client-dev \
    libkrb5-dev \
    unzip \
    --no-install-recommends && \
    rm -r /var/lib/apt/lists/*

RUN docker-php-ext-install mysqli

RUN pecl download imap && \
    tar -xf imap-*.tgz && \
    rm imap-*.tgz && \
    mv imap-* imap_src && \
    cd imap_src && \
    phpize && \
    ./configure --with-kerberos --with-imap-ssl && \
    make && \
    make install && \
    docker-php-ext-enable imap && \
    cd .. && \
    rm -rf imap_src

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /app