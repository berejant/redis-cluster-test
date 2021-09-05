FROM php:7.4-alpine

RUN apk --update add wget curl git build-base libmcrypt-dev libxml2-dev pcre-dev zlib-dev autoconf cyrus-sasl-dev libgsasl-dev oniguruma-dev libressl libressl-dev
RUN pecl channel-update pecl.php.net; docker-php-ext-install mbstring tokenizer xml pcntl

# Install Redis Extension
RUN printf "\n" | pecl install -o -f redis && rm -rf /tmp/pear && docker-php-ext-enable redis

# Clean up
RUN rm /var/cache/apk/*

WORKDIR /php
ENTRYPOINT ["/php/main.php"]
