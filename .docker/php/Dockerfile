FROM php:8.1-fpm-alpine
ENV XDEBUG_VERSION=3.3.2
ARG GITHUB_TOKEN

RUN apk add --no-cache nano icu-dev linux-headers $PHPIZE_DEPS
RUN pecl install xdebug-${XDEBUG_VERSION}
RUN docker-php-ext-install bcmath intl pdo_mysql
RUN docker-php-ext-enable xdebug

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer config --global github-oauth.github.com ${GITHUB_TOKEN}

CMD ["php-fpm"]
