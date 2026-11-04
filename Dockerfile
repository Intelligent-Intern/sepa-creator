FROM php:8.4-cli-bookworm@sha256:54b467ede1db96c66675e7b78457dd43729d0e7be31978d4a1e5c069ceabcf76
RUN php -r 'foreach(["dom","libxml","json"] as $e)if(!extension_loaded($e))exit(1);' \
    && groupadd --gid 10001 sepa && useradd --uid 10001 --gid 10001 --no-create-home sepa
WORKDIR /app
COPY src ./src
COPY resources ./resources
COPY tests ./tests
COPY examples ./examples
COPY bin ./bin
COPY composer.json LICENSE THIRD_PARTY_NOTICES.md README.md ./
USER 10001:10001
ENTRYPOINT ["php", "/app/bin/sepa-creator"]
CMD ["--help"]
