#!/usr/bin/env bash
set -e

docker build --platform=linux/amd64 -t flare-daemon-builder -f docker/Dockerfile.build .

docker run --rm -v "$(pwd)":/app --entrypoint composer flare-daemon-builder \
    install --prefer-dist --no-dev --no-interaction --classmap-authoritative

docker run --rm -v "$(pwd)":/app flare-daemon-builder
