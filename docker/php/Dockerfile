FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    linux-headers \
    $PHPIZE_DEPS \
    curl

RUN docker-php-ext-install -j$(nproc) \
    ctype \
    intl \
    opcache \
    pdo_mysql \
    zip

# Xdebug (явная версия и неинтерактивный режим)
RUN pecl channel-update pecl.php.net \
    && pecl install xdebug-3.4.2 \
    && docker-php-ext-enable xdebug

# Composer (PHP from this image, not apk php84)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

WORKDIR /var/www/html
