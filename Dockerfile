FROM python:3.12-slim-bookworm AS ocr
COPY scripts/ocr/requirements.txt /tmp/ocr-requirements.txt
RUN python -m venv /opt/ocr && /opt/ocr/bin/pip install --no-cache-dir -r /tmp/ocr-requirements.txt
COPY scripts/ocr/download_models.py /tmp/download_models.py
RUN /opt/ocr/bin/python /tmp/download_models.py /opt/ocr/models
FROM php:8.4-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libicu-dev libonig-dev libcurl4-openssl-dev unzip tesseract-ocr tesseract-ocr-spa poppler-utils libgl1 libglib2.0-0 libgomp1 libexpat1 libsqlite3-0 libbz2-1.0 liblzma5 libffi8 libreadline8 \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 pdo_mysql gd zip intl mbstring curl \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=ocr /opt/ocr /opt/ocr
COPY --from=ocr /usr/local/bin/python3.12 /usr/local/bin/python3.12
COPY --from=ocr /usr/local/lib/libpython3.12.so.1.0 /usr/local/lib/libpython3.12.so.1.0
COPY --from=ocr /usr/local/lib/python3.12 /usr/local/lib/python3.12
RUN ln -s python3.12 /usr/local/bin/python3 \
    && ln -s python3.12 /usr/local/bin/python \
    && ldconfig \
    && /opt/ocr/bin/python --version
WORKDIR /var/www/app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --classmap-authoritative --no-scripts \
    && composer clear-cache
COPY app/ app/
COPY config/example.php config/cloud.php config/
COPY database/ database/
COPY public/ public/
COPY views/ views/
COPY scripts/mail-worker.php scripts/mail-worker-smtp.php scripts/
COPY scripts/ocr/ scripts/ocr/
RUN /opt/ocr/bin/python scripts/ocr/selftest.py /opt/ocr/models
COPY docker/start-app.sh /usr/local/bin/start-app.sh
COPY docs/manual.pdf docs/manual.pdf
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
RUN mkdir -p storage/uploads storage/cache storage/sessions \
    && chown -R www-data:www-data storage \
    && sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i 's/\r$//' /usr/local/bin/start-app.sh \
    && sh -n /usr/local/bin/start-app.sh
EXPOSE 10000
CMD ["sh", "/usr/local/bin/start-app.sh"]
