FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    git curl zip unzip bash \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    libzip-dev oniguruma-dev icu-dev $PHPIZE_DEPS

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Install dependencies first (cache layer)
COPY composer.json composer.lock* ./
RUN composer install --no-scripts --no-autoloader --no-interaction 2>/dev/null || true

# Copy full app
COPY . .
RUN composer dump-autoload --optimize 2>/dev/null || true
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
