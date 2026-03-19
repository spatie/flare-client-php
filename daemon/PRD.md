# Flare Daemon - Product Requirements Document

## Overview

The Flare Daemon is a long-running PHP process that accepts local HTTP requests from Flare client applications and
forwards them to Flare asynchronously.

The main goal is to reduce request-time latency in PHP applications:

- the PHP app sends payloads to a local daemon quickly
- the daemon buffers payloads per API key and type
- the daemon flushes those buffers to Flare in the background

If the daemon is unreachable, the client falls back to sending directly to Flare with `CurlSender` and logs the daemon
failure to an emergency logger.

The API key stays client-side. Clients keep sending `x-api-token` and the daemon forwards requests for multiple keys.

This document intentionally keeps the upstream Flare contract lightweight and stubbed. The final ingress API is still in
progress, so this PRD defines a reasonable placeholder shape that can be swapped later.

## Goals

- Reduce the latency impact of reporting, tracing, and logging in PHP requests
- Keep the client integration small and framework-agnostic
- Preserve payload delivery with direct fallback when the daemon is down
- Stay close to the existing client flow, especially for test payloads
- Support multiple Flare projects from one daemon process

## Non-Goals

- Disk persistence for buffered payloads
- TLS termination inside the daemon
- Automatic process supervision
- A web UI
- A finalized upstream batching contract

## Key Decisions

| Decision | Choice |
|---|---|
| Repository location | Same repo, in `daemon/` |
| Package name | `spatie/flare-daemon` |
| PHP version | 8.2+ |
| Local protocol | HTTP |
| Default listen address | `127.0.0.1:8787` |
| Client integration | `FlareConfig::daemon()` convenience method |
| Flare mode | No new `FlareMode` in v1; daemon is a transport choice |
| Fallback | If daemon is unreachable, fall back to direct `CurlSender` |
| Buffering | Per API key per payload type |
| Flush policy | Time-based and size-based |
| Test payloads | Enter the same buffer, then trigger an immediate flush |
| Upstream contract | Stubbed placeholder contract on `ingress.flareapp.io` |
| Quota handling | Example implementation based on HTTP `429` |
| Status endpoint | Exposes raw API keys |
| Update detection | Watch `composer.lock` and self-shutdown gracefully |
| Distribution | PHAR + Docker image |

## Current Upstream Behavior We Can Already Mirror

The sibling `flare-ingress-cf-worker` is useful as an example source of response shapes, even though the final daemon
upstream contract is not fixed yet.

Observed patterns there:

- success returns `201`
- invalid method returns `405` with plain text
- missing API key returns `401` with plain text
- invalid API key returns `403` with plain text
- quota and rate limits return `429` with plain-text bodies such as `Trace quota exceeded` and `Rate limit exceeded`
- validation failures return `422` with JSON like:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "resourceSpans.0.scopeSpans.0.spans.0.traceId": [
      "The traceId field is required in this resourceSpan."
    ]
  }
}
```

The daemon should therefore treat upstream bodies as either plain text or JSON, and not assume one fixed format.

## Setup & Usage

### Running the daemon

**Docker**

```bash
docker run -d \
  --name flare-daemon \
  -p 8787:8787 \
  spatie/flare-daemon
```

**PHAR**

```bash
php daemon.phar
```

When running the PHAR on a VPS, use a process manager such as `systemd` or `supervisord`.

Set `FLARE_COMPOSER_LOCK` when the daemon should watch the application's `composer.lock` and gracefully stop after a
dependency update.

### Connecting a PHP application

```php
use Spatie\FlareClient\FlareConfig;

FlareConfig::make('your-api-key')
    ->daemon('http://127.0.0.1:8787');
```

The client still sends the API key in the `x-api-token` header.

### Verifying the daemon

```bash
curl http://127.0.0.1:8787/health

curl http://127.0.0.1:8787/status
```

## Client-Side Changes

## 1. FlareConfig

The daemon should be exposed as a convenience method on top of the existing sender configuration seam.

```php
public function daemon(string $daemonUrl = 'http://127.0.0.1:8787', array $config = []): static
{
    return $this->sendUsing(DaemonSender::class, [
        ...$config,
        'daemon_url' => rtrim($daemonUrl, '/'),
    ]);
}
```

Important: v1 does **not** need a dedicated `FlareMode::Daemon`.

Reason:

- the daemon does not change whether reporting, tracing, or logging are enabled
- it only changes how payloads are transported
- this keeps `FlareProvider` simpler

`FlareMode` can stay:

```php
enum FlareMode
{
    case Disabled;
    case Ignition;
    case Enabled;
}
```

## 2. Emergency Logger

The emergency logger is sender-owned configuration on `DaemonSender`, passed through `sendUsing()` or `daemon()` using the `emergency_logger` config key.

Default implementation: a very small PSR-3 logger that writes to `php://stderr`.

## 3. DaemonSender

**File:** `src/Senders/DaemonSender.php`

`DaemonSender` implements the existing `Sender` interface.

To stay compatible with the current sender construction in `FlareProvider`, the constructor should remain config-based:

```php
class DaemonSender implements Sender
{
    public function __construct(
        protected array $config = [],
    ) {
    }
}
```

Expected config keys:

- `daemon_url`
- `timeout`
- `test_timeout`
- `fallback_sender_config`
- `emergency_logger`

### Normal payload behavior

For normal payloads:

1. POST to `{daemonUrl}/v1/{errors|traces|logs}`
2. Expect `202 Accepted`
3. If the daemon is unreachable, times out, or refuses the connection:
   - log the daemon failure to the emergency logger
   - send the same payload directly with `CurlSender`
4. If the direct fallback also fails, let that exception bubble up to `Api`

### Test payload behavior

For test payloads:

1. add `X-Flare-Test: 1`
2. use a longer timeout
3. do **not** fall back
4. return the daemon's final upstream response to the caller

This preserves the purpose of test mode: verify the daemon path itself.

### Headers sent to the daemon

```http
X-API-Token: {apiKey}
Content-Type: application/json
Accept: application/json
```

If the daemon URL is `http://127.0.0.1` or `http://localhost`, SSL verification is irrelevant because the request is
plain HTTP. If the daemon is ever configured with HTTPS, verification should stay enabled by default.

## 4. Tester

The existing `Tester` already sends test payloads with `test: true`. That is enough for v1.

No large Tester redesign is required in this package.

Framework-specific commands may still wrap Tester and provide better terminal output, but the base client only needs:

- `report()` test payload
- `trace()` test payload
- `log()` test payload

## Daemon Package Structure

Keep the package small. v1 does not need a large abstraction tree.

```text
daemon/
├── src/
│   ├── daemon.php
│   ├── Server.php
│   ├── Ingest.php
│   ├── Buffer.php
│   ├── Upstream.php
│   ├── QuotaState.php
│   ├── CheckForUpdates.php
│   └── Support/
│       ├── Output.php
│       └── Json.php
├── tests/
│   ├── Feature/
│   └── Unit/
├── composer.json
├── phpstan.neon.dist
├── phpunit.xml.dist
├── box.json.dist
├── Dockerfile
└── build.sh
```

Notes:

- no custom loop wrapper in v1
- no browser factory in v1
- no clock abstraction in v1 unless tests prove it is needed
- no batching-specific classes yet because the upstream batch contract does not exist

## Runtime Architecture

```text
PHP App -> local daemon -> Flare ingress
```

### Local HTTP API

The daemon exposes:

- `POST /v1/errors`
- `POST /v1/traces`
- `POST /v1/logs`
- `GET /health`
- `GET /status`

### Health response

```json
{"status":"ok"}
```

### Status response

```json
{
  "keys": {
    "abc123": {
      "errors": {
        "buffered": 3,
        "paused": false,
        "retry_after": null,
        "last_429_reason": null
      },
      "traces": {
        "buffered": 0,
        "paused": true,
        "retry_after": "2026-03-17T12:00:00Z",
        "last_429_reason": "Trace quota exceeded"
      },
      "logs": {
        "buffered": 1,
        "paused": false,
        "retry_after": null,
        "last_429_reason": null
      }
    }
  }
}
```

Raw API keys are acceptable in this endpoint for v1.

## Entry Point

`daemon.php` should:

1. read environment variables
2. create a simple stdout/stderr logger
3. create one ReactPHP `Browser` configured with:
   - `withRejectErrorResponse(false)`
   - a default timeout
4. create `Ingest`
5. create `Server`
6. start `CheckForUpdates` when `FLARE_COMPOSER_LOCK` is set
7. call `Loop::run()`

Context7 confirmed that:

- `Browser::withRejectErrorResponse(false)` lets us inspect `4xx` and `5xx` responses
- `Browser` is immutable
- `HttpServer` handlers may return promises
- `Loop::run()` is valid when we want explicit process lifetime

## Local Request Handling

### Request validation

For incoming daemon requests:

- missing `x-api-token` -> `422` with JSON `{"message":"Missing API key"}`
- unsupported method -> `405`
- unknown route -> `404`
- invalid JSON -> `422`

### Success response

For normal payloads, the daemon returns:

```http
202 Accepted
```

as soon as the payload has been accepted into the in-memory buffer.

## Buffering Model

Buffers are kept per:

- API key
- entity type (`errors`, `traces`, `logs`)

Each buffered item stores:

- the original payload
- arrival time
- whether it is a test payload
- an optional resolver for the waiting HTTP test response

### Flush triggers

Flush a buffer when any of these is true:

- the buffer reaches the byte threshold
- the oldest item has been waiting for 10 seconds
- a test payload arrives
- shutdown is in progress

### Important v1 simplification

The daemon **buffers locally** but forwards **one payload per upstream request** for now.

Reason:

- there is no finalized upstream batch contract yet
- this keeps the daemon close to the current client behavior
- it lets us ship the daemon before the final batch API exists

When the real batch API is ready, only `Upstream` should need to change.

## Test Payload Flow

Test payloads should go through the same buffer as normal payloads, but they trigger an immediate flush.

Flow:

1. client sends request with `X-Flare-Test: 1`
2. daemon appends the payload to the normal per-key per-type buffer
3. daemon triggers an immediate flush for that buffer
4. daemon keeps the HTTP response open
5. when the flushed upstream request completes, the daemon resolves the waiting response

This keeps test payloads close to the production path while still giving immediate feedback.

If a buffer is paused due to quota:

- normal payloads are dropped
- test payloads are still accepted with a force flag and trigger a flush

That makes the integration check usable even while quota state exists locally.

## Upstream Contract

The final contract is not available yet, so v1 should isolate all upstream request building in `Upstream.php`.

For now, use this placeholder contract:

### Endpoint

```text
POST https://ingress.flareapp.io/v1/daemon/{errors|traces|logs}
```

### Headers

```http
X-API-Token: {projectApiKey}
Content-Type: application/json
Accept: application/json
User-Agent: FlareDaemon/{version}
Content-Encoding: gzip
```

### Request body

```json
{
  "payload": {},
  "sent_at": "2026-03-17T11:45:00Z",
  "source": {
    "transport": "daemon",
    "test": false
  }
}
```

Notes:

- `payload` is the exact original client payload
- each upstream request contains a single payload in v1
- the endpoint and envelope are placeholders and should live in one small class

### Expected example responses

The daemon should be able to handle at least:

- `201` or `202` success
- `403` invalid API key
- `422` validation failure with JSON body
- `429` rate limit or quota exceeded, with either:
  - plain-text body such as `Rate limit exceeded`
  - plain-text body such as `Trace quota exceeded`
  - JSON body containing a `message`
- any `5xx`

## Quota Handling

Quota logic in v1 should be intentionally simple.

### Example behavior

1. Any upstream `429` pauses that API key + entity type
2. If `Retry-After` is present, resume when that time is reached
3. If `Retry-After` is absent, retry with a probe flush every 60 seconds
4. Store the best-effort reason string for status/logging

Reason extraction order:

1. JSON `message`
2. plain-text body
3. fallback string `HTTP 429`

The daemon may log more detailed distinctions such as `Rate limit exceeded` vs `Trace quota exceeded`, but branching
logic should depend on the status code first, not on fragile body parsing.

## Upstream Response Handling

### Success

- `201` or `202`: item is considered delivered

### Permanent failure

- `403`: log prominently and pause all types for that API key until restart

### Validation failure

- `422`: log the response body and drop the item

### Rate limit / quota

- `429`: pause that key + type according to the quota rules above

### Other failure

- log status code and a truncated body
- drop the item

### Body parsing

The daemon must tolerate:

- JSON bodies
- plain-text bodies
- empty bodies

## Shutdown Behavior

The daemon should support graceful shutdown.

On shutdown:

1. stop accepting new work
2. trigger an immediate flush for all buffers
3. wait for in-flight upstream requests
4. exit

If shutdown was triggered by `composer.lock` changes, this flow is the expected behavior.

## Update Detection

`CheckForUpdates` is a real product requirement in v1.

Behavior:

- on startup, hash the configured `composer.lock`
- every 60 seconds, hash it again
- if the hash changes, start graceful shutdown
- if the file is temporarily unreadable, skip that check

This is especially relevant for VPS or vendor-installed PHAR setups.

## Logging

Daemon logs should go to stdout/stderr with a small helper, not a large logging subsystem.

Required log events:

- daemon started
- daemon stopped
- listening address
- upstream request failed
- daemon fallback triggered on the client side
- `429` pause/resume events
- `composer.lock` change detected

## Dependencies

Suggested daemon dependencies:

```json
{
  "require": {
    "php": "^8.2",
    "ext-zlib": "*",
    "psr/http-message": "^1.1|^2.0",
    "react/event-loop": "^1.5",
    "react/http": "^1.11",
    "react/promise": "^3.2",
    "react/socket": "^1.16"
  },
  "require-dev": {
    "phpunit/phpunit": "^11.5",
    "pestphp/pest": "^4.0",
    "phpstan/phpstan": "^2.1",
    "react/async": "^4.3",
    "symfony/process": "^7.2"
  }
}
```

Notes:

- use PHPUnit as the underlying test framework
- Pest may be used as a thin syntax layer for consistency with this repository
- `react/stream` is only needed if implementation later proves it necessary

## Testing

The daemon must be well tested.

Testing is a product requirement, not cleanup work to add at the end.

The suite should use a tight feedback loop:

1. write or adjust a unit test for the small rule or edge case being changed
2. verify the behavior again with a feature test that exercises the daemon more end to end
3. only keep tests that prove observable behavior or protect a real regression

The test suite should prefer testing the implementation's behavior, not mirroring private code structure.

Avoid meaningless tests such as:

- asserting trivial getters or internal state that users never observe
- snapshotting arbitrary implementation details
- duplicating the same assertion shape across unit and feature tests without adding confidence
- testing mocks more than the daemon itself

Prefer tests that prove real outcomes such as:

- payloads are buffered or flushed when expected
- fallback happens only when the daemon is unreachable
- `429` responses pause ingestion and later resume it
- test payloads enter the normal buffer and force an immediate flush
- shutdown drains in-flight work
- `composer.lock` changes trigger graceful self-shutdown

Test pyramid for v1:

- unit tests for tight state transitions and edge cases
- feature tests for real request/response flows and daemon lifecycle behavior
- very few pure mock-style tests

### Unit tests

- `BufferTest`
- `QuotaStateTest`
- `CheckForUpdatesTest`
- `UpstreamResponseHandlingTest`
- `ServerRequestValidationTest`

### Feature tests

Run the daemon in-process on an ephemeral port and use a fake upstream HTTP server.

Cover:

- normal buffering and timed flush
- size-based flush
- multi-key isolation
- fallback handling of `403`, `422`, `429`, `5xx`
- test payloads entering the normal buffer and triggering immediate flush
- `GET /health`
- `GET /status`
- graceful shutdown after `composer.lock` changes

This is simpler than building a large family of socket and loop stubs up front, and it keeps the suite focused on how
the daemon really behaves.

## Packaging

### PHAR

Use Box with PHP-Scoper.

### Docker

Ship a small `php:8.2-cli-alpine`-based image that runs the PHAR.

## Out of Scope For v1

- upstream batch sending
- disk-backed retry queues
- TLS in the daemon itself
- key eviction
- a browser UI
- a custom `FlareMode::Daemon`
