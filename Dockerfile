FROM composer:2.8@sha256:5248900ab8b5f7f880c2d62180e40960cd87f60149ec9a1abfd62ac72a02577c AS composer-binary
FROM php:8.4-cli-bookworm@sha256:54b467ede1db96c66675e7b78457dd43729d0e7be31978d4a1e5c069ceabcf76 AS dependencies
COPY --from=composer-binary /usr/bin/composer /usr/local/bin/composer
RUN apt-get update && apt-get install -y --no-install-recommends unzip && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY composer.json composer.lock ./
COPY src ./src
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-plugins --prefer-dist --optimize-autoloader

FROM php:8.4-cli-bookworm@sha256:54b467ede1db96c66675e7b78457dd43729d0e7be31978d4a1e5c069ceabcf76
RUN php -r 'foreach(["dom","libxml","json"] as $e)if(!extension_loaded($e))exit(1);' \
    && groupadd --gid 10001 sepa && useradd --uid 10001 --gid 10001 --no-create-home sepa
WORKDIR /app
COPY --from=dependencies /app/vendor ./vendor
COPY src ./src
COPY resources ./resources
COPY tests ./tests
COPY examples ./examples
COPY bin ./bin
COPY composer.json composer.lock LICENSE THIRD_PARTY_NOTICES.md README.md UPSTREAM_COMPARISON.md ./
USER 10001:10001
ENTRYPOINT ["php", "/app/bin/sepa-creator"]
CMD ["--help"]
