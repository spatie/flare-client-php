#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILD_DIR="$(mktemp -d "${TMPDIR:-/tmp}/flare-daemon-build.XXXXXX")"
BOX_CACHE_DIR="${ROOT_DIR}/.box/bin"
BOX_PHAR="${BOX_CACHE_DIR}/box.phar"
OUTPUT_PHAR="${ROOT_DIR}/daemon.phar"

cleanup() {
    rm -rf "${BUILD_DIR}"
}

ensure_box() {
    if command -v box >/dev/null 2>&1; then
        BOX_CMD=(box)

        return
    fi

    mkdir -p "${BOX_CACHE_DIR}"

    if [[ ! -f "${BOX_PHAR}" ]]; then
        curl -fsSL "https://github.com/box-project/box/releases/latest/download/box.phar" -o "${BOX_PHAR}"
        chmod +x "${BOX_PHAR}"
    fi

    BOX_CMD=(php "${BOX_PHAR}")
}

trap cleanup EXIT

(
    cd "${ROOT_DIR}"
    tar \
        --exclude='./.box' \
        --exclude='./.build' \
        --exclude='./.git' \
        --exclude='./daemon.phar' \
        --exclude='./vendor' \
        -cf - .
) | (
    cd "${BUILD_DIR}"
    tar -xf -
)

composer install \
    --working-dir="${BUILD_DIR}" \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader

ensure_box

(
    cd "${BUILD_DIR}"
    "${BOX_CMD[@]}" compile -c box.json.dist
)

cp "${BUILD_DIR}/daemon.phar" "${OUTPUT_PHAR}"

printf 'Built %s\n' "${OUTPUT_PHAR}"
