FROM php:8.5-cli-bookworm

# 1. Install system dependencies
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

# 2. Install Standard Core Extensions (mysqli)
RUN docker-php-ext-install mysqli

# 3. Install IMAP (Manual Build from PECL)
# Fix: We remove the .tgz file immediately after extraction so 'mv' only sees the directory.
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