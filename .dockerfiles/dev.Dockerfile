# syntax=docker/dockerfile:1

FROM php:8.2-apache-bookworm AS minimal
RUN apt-get update && apt-get install -y \
    build-essential \
    libc-client-dev \
    libkrb5-dev \
    openssl \
    curl
RUN docker-php-ext-install pdo pdo_mysql
RUN docker-php-ext-configure imap --with-kerberos --with-imap-ssl && docker-php-ext-install -j$(nproc) imap
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php \
    && php -r "unlink('composer-setup.php');" \
    && mv composer.phar /usr/local/bin/composer
COPY .vhosts/000-default.conf /etc/apache2/sites-available/000-default.conf

FROM minimal AS base
RUN apt-get update && apt-get install -y \
    libgmp-dev \
    libpng-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libicu-dev \
    libzip-dev \
    zlib1g-dev \
    mysql-common \
    mariadb-client \
    locales \
    zip
RUN docker-php-ext-install opcache exif shmop iconv zip
RUN docker-php-ext-configure intl && docker-php-ext-install intl
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install gd

FROM composer:latest AS dev-deps
WORKDIR /tmp
RUN --mount=type=bind,source=./composer.json,target=composer.json \
    --mount=type=cache,target=/tmp/cache \
    composer install --no-interaction

FROM base AS development
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY --from=dev-deps tmp/vendor/ /var/www/html/vendor
RUN a2enmod headers
RUN a2enmod rewrite
RUN service apache2 restart
RUN export PATH=$PATH:/var/www/html
RUN ln -s /var/www/html/cli /usr/local/bin/texo
EXPOSE 80