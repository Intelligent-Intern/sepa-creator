FROM composer:2@sha256:5190d9b445add2fa26090108273cb1ef1e1af98175201c737b85b3c4f90fbf7c AS composer-binary
FROM cgr.dev/chainguard/wolfi-base@sha256:24f549a7fcace88e69b9557941168c74645688ecc4acf7fb6d734a6b74e9019d AS php-runtime
RUN apk upgrade --no-cache && apk add --no-cache \
    'php-8.5>=8.5.13-r1' php-8.5-config php-8.5-dom php-8.5-xmlreader \
    'libxml2>=2.15.4-r0' \
    'glibc-2.44>=2.44-r16' 'glibc-2.44-locale-posix>=2.44-r16' 'ld-linux-2.44>=2.44-r16' 'libcrypt1-2.44>=2.44-r16' \
    && php -r 'foreach(["dom","libxml","json","xmlreader"] as $e)if(!extension_loaded($e))exit(1);if(version_compare(LIBXML_DOTTED_VERSION,"2.15.4","<"))exit(1);' \
    && addgroup -g 10001 sepa && adduser -D -H -u 10001 -G sepa sepa
COPY docker/php-security.ini /etc/php/conf.d/zz-security.ini

FROM php-runtime AS dependencies
COPY --from=composer-binary /usr/bin/composer /usr/local/bin/composer
RUN apk add --no-cache php-8.5-phar php-8.5-openssl php-8.5-iconv unzip
WORKDIR /app
COPY composer.json composer.lock ./
COPY src ./src
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-plugins --prefer-dist --optimize-autoloader

FROM php-runtime
ARG VCS_REF=unknown
LABEL org.opencontainers.image.source="https://github.com/Intelligent-Intern/sepa-creator" \
      org.opencontainers.image.version="0.1.0" \
      org.opencontainers.image.revision="${VCS_REF}"
WORKDIR /app
COPY --from=dependencies /app/vendor ./vendor
COPY src ./src
COPY resources ./resources
COPY tests ./tests
COPY examples ./examples
COPY bin ./bin
COPY tools/check-runtime.php ./tools/check-runtime.php
COPY composer.json composer.lock SECURITY.md LICENSE THIRD_PARTY_NOTICES.md README.md UPSTREAM_COMPARISON.md ./
RUN php tools/check-runtime.php
USER 10001:10001
ENTRYPOINT ["php", "/app/bin/sepa-creator"]
CMD ["--help"]
