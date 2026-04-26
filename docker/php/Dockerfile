FROM php:8.3-fpm-alpine

ARG UID=1000
ARG GID=1000

RUN apk add --no-cache \
        bash \
        git \
        curl \
        zip \
        unzip \
        icu-dev \
        oniguruma-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        nodejs \
        npm \
        mysql-client \
        $PHPIZE_DEPS

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        bcmath \
        intl \
        zip \
        gd \
        pcntl \
        exif

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

RUN set -eux; \
    if getent group ${GID} >/dev/null; then \
        existing_group=$(getent group ${GID} | cut -d: -f1); \
        echo "GID ${GID} already exists as ${existing_group}, reusing"; \
    else \
        addgroup -g ${GID} app; \
        existing_group=app; \
    fi; \
    if getent passwd ${UID} >/dev/null; then \
        echo "UID ${UID} already exists, reusing"; \
    else \
        adduser -D -u ${UID} -G ${existing_group} app; \
    fi

WORKDIR /var/www

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER ${UID}:${GID}

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
