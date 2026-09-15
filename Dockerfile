ARG PHP_IMAGE=php:8.4-cli-alpine
FROM composer:2 AS composer
FROM ${PHP_IMAGE} AS development
ARG OPENSWOOLE_VERSION=26.2.0
ARG GRPC_VERSION=1.78.0
RUN apk add --no-cache $PHPIZE_DEPS linux-headers openssl-dev curl-dev nghttp2-dev oniguruma-dev libxml2-dev sqlite-dev protobuf protobuf-dev python3 py3-pip git unzip \
    && docker-php-ext-install -j4 mbstring dom pdo_sqlite \
    && pecl install -D 'enable-openssl="yes" enable-http2="yes" enable-sockets="no" enable-hook-curl="no" enable-mysqlnd="no"' openswoole-${OPENSWOOLE_VERSION} \
    && MAKEFLAGS='-j4' pecl install grpc-${GRPC_VERSION} \
    && docker-php-ext-enable openswoole grpc
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
RUN docker-php-ext-install bcmath
RUN addgroup -g 1000 sdk && adduser -D -u 1000 -G sdk sdk
WORKDIR /app
USER sdk
ENV COMPOSER_HOME=/tmp/composer
CMD ["sleep", "infinity"]
