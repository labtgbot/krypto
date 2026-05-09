FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        default-mysql-client \
    && docker-php-ext-install pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
