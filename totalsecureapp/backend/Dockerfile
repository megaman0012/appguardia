FROM php:8.3-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# El memory_limit por defecto de PHP (128M) no alcanza para la suite completa:
# `php artisan test` moria con "Allowed memory size exhausted" a mitad de camino.
RUN printf 'memory_limit=512M\n' > /usr/local/etc/php/conf.d/zz-memory.ini

RUN sed -i 's/^user = www-data/user = 1000/' /usr/local/etc/php-fpm.d/www.conf \
 && sed -i 's/^group = www-data/group = 1000/' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www

EXPOSE 9000
CMD ["php-fpm"]
