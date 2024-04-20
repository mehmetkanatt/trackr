FROM php:8.2-fpm

RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev zip libicu-dev apache2 && \
    apt-get clean all && \
    rm -rf /var/lib/apt/lists/*

RUN rm /etc/apache2/sites-enabled/000-default.conf
COPY fpm.conf /etc/apache2/sites-enabled/trackr.conf
COPY www.conf /usr/local/etc/php-fpm.d/zzz-trackr-fpm.conf

RUN docker-php-ext-configure intl && \
    docker-php-ext-install pdo pdo_mysql zip bcmath sockets intl && \
    pecl install redis && \
    docker-php-ext-enable redis.so

RUN rm -rf /tmp/pear

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

RUN a2enmod proxy_fcgi rewrite headers ssl

CMD ["sh", "-c", "service apache2 start && php-fpm"]