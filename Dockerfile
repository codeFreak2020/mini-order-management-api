FROM php:8.3-fpm-alpine

# Install build dependencies and PHP extensions.
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
        libzip-dev icu-dev libpng-dev oniguruma-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring bcmath gd intl zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Composer.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy application code.
COPY . .

# Install production dependencies.
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Ensure storage is writable by php-fpm (www-data).
RUN chown -R www-data:www-data /var/www

EXPOSE 9000

CMD ["php-fpm"]
