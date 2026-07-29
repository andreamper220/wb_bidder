FROM php:8.4-fpm-alpine

RUN apk add --no-cache git unzip libpq-dev icu-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql intl opcache bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

CMD ["php-fpm"]
