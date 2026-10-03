FROM php:8.1-cli-alpine

RUN apk add --no-cache linux-headers $PHPIZE_DEPS \
	&& docker-php-ext-install pdo_mysql \
	&& pecl install xdebug \
	&& docker-php-ext-enable xdebug \
	&& apk del $PHPIZE_DEPS

ENV XDEBUG_MODE=coverage

COPY --from=composer:2.10 /usr/bin/composer /usr/bin/composer

ENTRYPOINT ["composer"]
