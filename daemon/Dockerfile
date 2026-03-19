FROM composer:2 AS build

WORKDIR /app
COPY . .
RUN composer check-platform-reqs --no-dev
RUN bash ./build.sh

FROM php:8.2-cli-alpine

RUN apk add --no-cache curl

WORKDIR /app
COPY --from=build /app/daemon.phar /app/daemon.phar
COPY docker/entrypoint.sh /app/entrypoint.sh
RUN chmod +x /app/entrypoint.sh

ENV FLARE_DAEMON_LISTEN=0.0.0.0:8787

EXPOSE 8787

HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD curl -sf http://127.0.0.1:8787/health || exit 1

ENTRYPOINT ["/app/entrypoint.sh"]
