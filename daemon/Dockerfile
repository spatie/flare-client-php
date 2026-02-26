FROM php:8.2-cli-alpine

RUN apk add --no-cache zlib-dev \
    && docker-php-ext-install zlib

COPY build/daemon.phar /opt/flare/daemon.phar
COPY docker/entrypoint.sh /opt/flare/entrypoint.sh

RUN chmod +x /opt/flare/entrypoint.sh

EXPOSE 8787

ENTRYPOINT ["/opt/flare/entrypoint.sh"]
