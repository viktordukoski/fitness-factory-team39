FROM php:8.3.35-apache-bookworm

# Separate completed build steps can be reused when the builder retains its cache.
# An interrupted step still has to run again.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libonig-dev libpng-dev libjpeg62-turbo-dev libwebp-dev \
    && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install -j2 pdo_mysql opcache
RUN docker-php-ext-install -j2 mbstring
RUN docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j2 gd
RUN docker-php-ext-install -j2 intl
RUN a2enmod rewrite headers

WORKDIR /var/www/html
COPY app/ app/
COPY public/ public/
COPY database/ database/
COPY bin/ bin/
COPY docker/wait-for-db.php docker/wait-for-db.php
COPY docker/health.php docker-health.php
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/fitness-factory.ini
COPY docker/mpm-prefork.conf /etc/apache2/mods-available/mpm_prefork.conf
RUN mkdir -p public/uploads \
    && chmod 755 bin/entrypoint.sh \
    && chown -R root:root /var/www/html \
    && chown www-data:www-data public/uploads

EXPOSE 80
ENTRYPOINT ["/var/www/html/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
