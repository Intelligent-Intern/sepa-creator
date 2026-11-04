FROM composer:2@sha256:5190d9b445add2fa26090108273cb1ef1e1af98175201c737b85b3c4f90fbf7c AS composer-binary
FROM php:8.5.12-cli-trixie@sha256:9236fd373d3bcda0659cf94838a744bd79791419292958963f8c6b34a0071554 AS dependencies
COPY --from=composer-binary /usr/bin/composer /usr/local/bin/composer
RUN apt-get update && apt-get install -y --no-install-recommends unzip && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY composer.json composer.lock ./
COPY src ./src
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-plugins --prefer-dist --optimize-autoloader

FROM php:8.5.12-cli-trixie@sha256:9236fd373d3bcda0659cf94838a744bd79791419292958963f8c6b34a0071554
RUN apt-get update && apt-get upgrade -y \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/* \
    && php -r 'foreach(["dom","libxml","json"] as $e)if(!extension_loaded($e))exit(1);' \
    && groupadd --gid 10001 sepa && useradd --uid 10001 --gid 10001 --no-create-home sepa
WORKDIR /app
COPY --from=dependencies /app/vendor ./vendor
COPY src ./src
COPY resources ./resources
COPY tests ./tests
COPY examples ./examples
COPY bin ./bin
COPY docker/php-security.ini /usr/local/etc/php/conf.d/zz-security.ini
COPY tools/check-runtime.php ./tools/check-runtime.php
COPY composer.json composer.lock SECURITY.md LICENSE THIRD_PARTY_NOTICES.md README.md UPSTREAM_COMPARISON.md ./
RUN php tools/check-runtime.php
USER 10001:10001
ENTRYPOINT ["php", "/app/bin/sepa-creator"]
CMD ["--help"]
