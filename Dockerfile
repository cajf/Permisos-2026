FROM php:8.4-apache
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libicu-dev libonig-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 pdo_mysql gd zip intl mbstring \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --classmap-authoritative --no-scripts \
    && composer clear-cache
COPY app/ app/
COPY config/example.php config/cloud.php config/
COPY database/ database/
COPY public/ public/
COPY views/ views/
COPY docs/manual.pdf docs/manual.pdf
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
RUN mkdir -p storage/uploads storage/cache storage/sessions \
    && chown -R www-data:www-data storage \
    && sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf
EXPOSE 10000
CMD ["apache2-foreground"]
