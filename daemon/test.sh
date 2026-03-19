#!/usr/bin/env bash

set -euo pipefail

DAEMON_URL="http://127.0.0.1:8787"

usage() {
    printf 'Usage: %s [-u DAEMON_URL] API_KEY\n' "$(basename "$0")"
    printf '\nSend a smoke-test error payload through the daemon.\n'
    printf '\nOptions:\n'
    printf '  -u URL   Daemon URL (default: %s)\n' "${DAEMON_URL}"
    printf '  -h       Show this help\n'
    exit 1
}

while getopts ":u:h" opt; do
    case ${opt} in
        u) DAEMON_URL="${OPTARG}" ;;
        h) usage ;;
        :) printf 'Option -%s requires an argument.\n' "${OPTARG}" >&2; exit 1 ;;
        \?) printf 'Unknown option: -%s\n' "${OPTARG}" >&2; exit 1 ;;
    esac
done

shift $((OPTIND - 1))

if [[ $# -lt 1 ]]; then
    printf 'Error: API_KEY is required.\n\n' >&2
    usage
fi

API_KEY="$1"

# ── Step 1: Health check ────────────────────────────────────────────

printf '=> Health check ... '

HEALTH_STATUS=$(curl -s -o /dev/null -w '%{http_code}' "${DAEMON_URL}/health" 2>/dev/null) || {
    printf 'FAIL\n   Daemon is not reachable at %s\n' "${DAEMON_URL}" >&2
    exit 1
}

if [[ "${HEALTH_STATUS}" != "200" ]]; then
    printf 'FAIL (HTTP %s)\n' "${HEALTH_STATUS}" >&2
    exit 1
fi

printf 'OK\n'

# ── Step 2: Send a normal error payload ─────────────────────────────

NOW_NS=$(date +%s)000000000
UUID=$(uuidgen 2>/dev/null | tr '[:upper:]' '[:lower:]' || printf '%04x%04x-%04x-%04x-%04x-%04x%04x%04x' $RANDOM $RANDOM $RANDOM $RANDOM $RANDOM $RANDOM $RANDOM $RANDOM)

PAYLOAD=$(cat <<JSON
{
    "exceptionClass": "RuntimeException",
    "seenAtUnixNano": ${NOW_NS},
    "message": "Smoke test from test.sh at $(date '+%Y-%m-%d %H:%M:%S')",
    "code": 0,
    "solutions": [],
    "stacktrace": [
        {
            "file": "test.sh",
            "lineNumber": 1,
            "method": "main",
            "class": null,
            "codeSnippet": {},
            "arguments": [],
            "isApplicationFrame": true
        }
    ],
    "previous": [],
    "openFrameIndex": null,
    "applicationPath": null,
    "trackingUuid": "${UUID}",
    "handled": null,
    "attributes": {
        "service.name": "flare-daemon-smoke-test",
        "flare.language.name": "PHP",
        "flare.entry_point.type": "cli",
        "flare.entry_point.value": "test.sh",
        "telemetry.sdk.name": "spatie/flare-client-php",
        "telemetry.sdk.version": "dev-main"
    },
    "events": [],
    "isLog": false,
    "overriddenGrouping": null
}
JSON
)

printf '=> Sending error payload ... '

SEND_STATUS=$(curl -s -o /dev/null -w '%{http_code}' \
    -X POST \
    -H "Content-Type: application/json" \
    -H "X-API-Token: ${API_KEY}" \
    -d "${PAYLOAD}" \
    "${DAEMON_URL}/v1/errors")

if [[ "${SEND_STATUS}" != "202" ]]; then
    printf 'FAIL (expected 202, got %s)\n' "${SEND_STATUS}" >&2
    exit 1
fi

printf '202 Accepted\n'

# ── Step 3: Poll /status until the buffer drains ────────────────────

printf '=> Waiting for buffer to drain '

DRAINED=0

for i in $(seq 1 10); do
    sleep 1
    printf '.'

    STATUS_BODY=$(curl -sf "${DAEMON_URL}/status" 2>/dev/null) || continue

    BUFFERED=$(printf '%s' "${STATUS_BODY}" | grep -o '"buffered":[0-9]*' | head -1 | cut -d: -f2 || true)

    if [[ -z "${BUFFERED}" || "${BUFFERED}" == "0" ]]; then
        DRAINED=1
        break
    fi
done

printf '\n'

if [[ "${DRAINED}" -eq 1 ]]; then
    printf '=> PASS — payload accepted and buffer drained.\n'
    printf '   Check the daemon logs and your Flare dashboard to confirm delivery.\n'
else
    printf '=> WARN — buffer has not drained after 10 seconds.\n' >&2
    printf '   The payload was accepted (202) but may not have been forwarded yet.\n' >&2
    printf '   Check daemon logs for errors (quota, network, invalid key).\n' >&2
    exit 1
fi
