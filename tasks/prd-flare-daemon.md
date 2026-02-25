# PRD: Flare Daemon

## Introduction

The Flare Daemon is a long-running PHP process that acts as a local proxy between Flare client applications and the Flare ingestion service. Instead of each PHP request sending payloads directly to Flare (and waiting for the HTTP response), clients send payloads to the daemon over a local TCP socket. The daemon buffers, batches, gzip-compresses, and forwards these payloads to Flare asynchronously — reducing latency impact on application requests.

The daemon **owns the API key**. Client applications don't need a Flare API key — they only need the daemon's TCP address (default `127.0.0.1:8787`). This centralizes key management and simplifies client configuration.

**Architecture:**

```
PHP App 1 ─┐              ┌─── POST /v1/errors ──┐
PHP App 2 ──┤── TCP ──▶  Daemon  ─── POST /v1/traces ──▶  Flare Ingress
PHP App N ─┘  (no key)    └─── POST /v1/logs  ──┘
                (has key)       GET /v1/usage
```

## Goals

- Eliminate HTTP latency from the PHP request lifecycle by offloading Flare communication to a background daemon
- Centralize API key management — clients connect to the daemon without credentials
- Buffer and batch payloads per type (errors, traces, logs) to reduce outbound HTTP requests
- Track quota per type and stop sending when limits are reached
- Provide a test flow that verifies the full pipeline (client → daemon → Flare) end-to-end
- Support deployment as both a Docker container and a standalone PHAR binary
- Add an emergency logger to the client so delivery failures are no longer silently swallowed

## User Stories

### US-001: Client daemon mode and configuration

**Description:** As a developer, I want to configure my PHP application to send payloads to the Flare daemon instead of directly to Flare, so that my application requests are not blocked by HTTP calls.

**Acceptance Criteria:**
- [ ] New `FlareMode::Daemon` enum case added to `src/Enums/FlareMode.php`
- [ ] `FlareConfig::daemon(string $daemonUrl = '127.0.0.1:8787')` method sets sender to `DaemonSender` and stores the daemon URL
- [ ] `FlareProvider` resolves `FlareMode::Daemon` when `$config->daemonUrl !== null` (takes precedence over API key check)
- [ ] No API key is required when using daemon mode
- [ ] `FlareMode::Daemon` behaves identically to `Enabled` for tracing, logging, and error reporting
- [ ] PHPStan passes

### US-002: Emergency logger for delivery failures

**Description:** As a developer, I want failed payload deliveries to be logged to a configurable PSR logger, so that I can diagnose delivery problems instead of them being silently swallowed.

**Acceptance Criteria:**
- [ ] `FlareConfig::emergencyLogger(LoggerInterface $logger)` method added
- [ ] `Api::sendEntity()` catch block calls `$this->emergencyLogger?->error('Flare delivery failed', ['exception' => $throwable])` when delivery fails in non-test mode
- [ ] Existing behavior preserved: test mode still re-throws, non-test mode without logger still silently returns
- [ ] Works with all senders (CurlSender, DaemonSender, etc.) since the catch is in `Api`
- [ ] PHPStan passes

### US-003: DaemonConnection — persistent TCP client

**Description:** As a developer, I want a persistent TCP connection to the daemon that stays open for the lifetime of the PHP process, so that multiple payloads (logs, traces, errors) are sent efficiently without per-payload TCP handshake overhead.

**Acceptance Criteria:**
- [ ] `DaemonConnection` class at `src/Support/DaemonConnection.php` with singleton pattern via `create(string $daemonUrl)`
- [ ] Socket is lazy-opened on first `write()`, `ping()`, or `status()` call
- [ ] All subsequent calls reuse the same socket
- [ ] If a write/read fails, the socket is closed and re-established on the next call (automatic reconnect)
- [ ] `__destruct` closes the socket when the PHP process ends
- [ ] `ping()` sends `PING` over socket, returns `true` if daemon responds `2:OK`, `false` if unreachable
- [ ] `status()` sends `STATUS` over socket, returns parsed JSON array (usage/quota info) or `null` if unreachable
- [ ] `readWithTimeout(int $seconds)` supports blocking reads with a timeout for test payload responses
- [ ] `static reset(): void` nulls the singleton and closes the socket (for test isolation)
- [ ] PHPStan passes

### US-004: DaemonSender — TCP payload sender

**Description:** As a developer, I want the Flare client to send payloads to the daemon over TCP using a length-prefixed protocol, so the daemon can reliably parse and forward them.

**Acceptance Criteria:**
- [ ] `DaemonSender` class at `src/Senders/DaemonSender.php` implements the existing `Sender` interface
- [ ] Payloads formatted as `{total_length}:{version}:{type}:{jsonPayload}` where version is `v1`
- [ ] Normal payloads: writes via `DaemonConnection::write()`, reads `2:OK` ack via `DaemonConnection::read()`
- [ ] Test payloads (`test: true`): appends `_test` to type, reads `2:OK` ack, then blocks to read Flare response via `readWithTimeout()`, parses `{length}:{type}:{statusCode}:{responseBody}` format, verifies type matches, calls response callback
- [ ] `onTestAck(Closure $callback)` registers a one-time callback that fires after the daemon ack and before blocking for the Flare response
- [ ] Throws `DaemonTimeoutException` when test response read times out
- [ ] On failure (daemon unreachable): throws exception (caught by `Api::sendEntity`)
- [ ] PHPStan passes

### US-005: Tester orchestration for all sender types

**Description:** As a developer, I want the `Tester` class to orchestrate sending test payloads for all three types (errors, traces, logs) and report progress through callbacks, so that both CLI commands and framework integrations can provide rich feedback.

**Acceptance Criteria:**
- [ ] `Tester` constructor accepts `$sender`, `$onInfo`, `$onWarning`, `$onError` closure parameters
- [ ] `test(array $types, int $daemonTimeout = 30): bool` method iterates `FlareEntityType::cases()`, skips disabled types with a warning, sends enabled types
- [ ] For `CurlSender`: reports "Sending test {name}..." then success/failure
- [ ] For `DaemonSender`: reports sending to daemon, "Daemon received test {name}", "Waiting for Flare response...", then success/failure
- [ ] Catches `DaemonTimeoutException`, `BadResponseCode`, and general `Throwable` with appropriate error messages
- [ ] `reportSupportInfo()` outputs support URLs and contact info via `onInfo`
- [ ] `platformInfo()` returns framework-agnostic info array (Platform, PHP, SDK, Curl, SSL)
- [ ] Returns `true` only if all enabled types passed
- [ ] PHPStan passes

### US-006: Daemon TCP server and payload parsing

**Description:** As a daemon operator, I want the daemon to accept persistent TCP connections from clients and parse length-prefixed payloads, so that multiple PHP applications can send payloads reliably.

**Acceptance Criteria:**
- [ ] `Server` class listens on configured address (default `127.0.0.1:8787`) using ReactPHP socket
- [ ] Accepts persistent TCP connections — connections stay open for multiple payloads
- [ ] `Payload` class parses `{length}:{version}:{type}:{data}` format, accumulates chunks until complete
- [ ] Validates protocol version is `v1`, rejects invalid versions
- [ ] Extracts type (`errors`, `traces`, `logs`, `errors_test`, `traces_test`, `logs_test`)
- [ ] `Payload::isTest()` returns true when type ends in `_test`
- [ ] `Payload::baseType()` strips `_test` suffix
- [ ] After a payload is complete, parser resets — leftover bytes from the current chunk feed into the next payload
- [ ] Responds `2:OK` for normal payloads (via `$connection->write()`, not `end()`)
- [ ] Supports `PING` command (responds `2:OK`, connection stays open)
- [ ] Supports `STATUS` command (responds `{length}:{jsonStatusPayload}`, connection stays open)
- [ ] Handles `close` event gracefully, logs connection errors
- [ ] PHPStan passes at level max
- [ ] Unit tests for `Payload`: parsing, chunked data, validation, type extraction, `isTest()`, `baseType()`
- [ ] Feature tests for `Server`: TCP handling, PING, invalid payloads, incomplete payloads, version mismatch, persistent connections

### US-007: Ingest — buffering and forwarding payloads to Flare

**Description:** As a daemon operator, I want the daemon to buffer incoming payloads per type and forward them to Flare in batches with gzip compression, so that outbound HTTP traffic is efficient and doesn't overwhelm Flare.

**Acceptance Criteria:**
- [ ] `Ingest` class manages three separate `StreamBuffer` instances, one per `FlareEntityType`
- [ ] `StreamBuffer` accumulates payloads; flushes when buffer exceeds ~6MB size threshold
- [ ] If buffer has data but is under threshold, flushes after 10-second timer
- [ ] Maximum 5 concurrent outbound HTTP requests (shared across all types)
- [ ] Forwards to correct endpoints: `POST {baseUrl}/v1/{type}?key={apiKey}` with headers: `x-api-token`, `Content-Type: application/json`, `Content-Encoding: gzip`, `Accept: application/json`, `User-Agent: FlareDaemon/{version}`
- [ ] Payloads are gzip-compressed before sending
- [ ] Response handling: 201 success, 403 stop all types + warn, 422 log body (stop if "Missing API key"), 429 distinguish rate limit (backoff) vs quota exceeded (pause type), other errors logged
- [ ] Response body >1005 bytes truncated in logs
- [ ] Force digest on shutdown: flush all buffers, wait for in-flight requests
- [ ] Pause/resume: over-quota type's `StreamBuffer` swapped to `NullBuffer` (drops payloads), restored on resume
- [ ] PHPStan passes at level max
- [ ] Unit tests for `StreamBuffer`: threshold, pull, flush, per-type isolation
- [ ] Feature tests for `Ingest`: buffering, batching, concurrent requests, quota stop/resume, error handling, force digest

### US-008: Test payload flow through the daemon

**Description:** As a developer running `flare:test`, I want test payloads to flow through the daemon and return the actual Flare response, so that I can verify the full pipeline end-to-end.

**Acceptance Criteria:**
- [ ] Test payloads (`_test` suffix) bypass quota checks — write to real `StreamBuffer` even when `NullBuffer` is active
- [ ] Test payloads trigger an immediate buffer flush (no waiting for timer or size threshold)
- [ ] Flush sends each payload as a separate HTTP request (no batching) so the test payload's response can be identified
- [ ] TCP connection reference stored as pending test connection for that type
- [ ] When Flare responds, daemon sends second message to pending test connections: `{length}:{type}:{statusCode}:{responseBody}`
- [ ] If Flare request fails (network error), pending test connections receive synthetic `{length}:{type}:503:{"message":"Upstream error"}`
- [ ] If daemon shuts down while test connection pending, force-digest flushes buffer and delivers response before closing
- [ ] Test payloads count toward local usage counters (same as normal)
- [ ] Multiple test types work sequentially on the same persistent connection
- [ ] PHPStan passes at level max
- [ ] Feature tests covering: immediate flush, response per type, quota bypass, upstream error, force digest, multiple types on same connection

### US-009: Quota management

**Description:** As a daemon operator, I want the daemon to track per-type quota and stop sending when limits are reached, so that payloads are dropped locally instead of being rejected by Flare.

**Acceptance Criteria:**
- [ ] `UsageRepository` class fetches `GET {baseUrl}/v1/usage?key={apiKey}` on startup
- [ ] `Usage` value object holds `errorsUsed`, `errorsLimit`, `tracesUsed`, `tracesLimit`, `logsUsed`, `logsLimit`, `resetAt`
- [ ] If any type is over limit on startup, immediately pauses that type
- [ ] Re-fetches usage every 24 hours via periodic timer
- [ ] After each successful ingest, increments local counters by payload array count; pauses type when counter reaches limit
- [ ] On 429 "quota exceeded" response from Flare, immediately pauses that type and schedules usage re-fetch
- [ ] Per-type independence: hitting error limit does not pause traces or logs
- [ ] Resume logic: when re-fetch shows a type is under quota, calls `Ingest::resumeIngestion($type)`
- [ ] If all types over quota, schedules re-fetch at `reset_at` time
- [ ] Retry strategy before first successful fetch: exponential backoff (2.5s, 5s, 10s, 15s, 30s, 60s, 120s, 240s, then 300s x12, then 3600s)
- [ ] Retry strategy after first successful fetch: retry every 300s, then 3600s after 13 consecutive failures
- [ ] PHPStan passes at level max
- [ ] Feature tests: quota fetch, daily refresh, per-type pause/resume, local counters, response-driven stop, retry strategies

### US-010: Daemon entry point, event loop, and logging

**Description:** As a daemon operator, I want the daemon to start up, wire all components together, and provide structured logging, so that the daemon runs reliably and is observable.

**Acceptance Criteria:**
- [ ] `daemon.php` entry point reads config from environment variables (`FLARE_API_KEY` required, others have defaults)
- [ ] `bootstrap.php` sets up autoloading (PHAR-safe)
- [ ] Custom `Loop` class wraps `LoopInterface`, tracks `running()` state
- [ ] `OutputWriter` uses async `WritableResourceStream` when loop is running, falls back to sync `fwrite` when not
- [ ] `Browser` wrapper around `React\Http\Browser` implements `Contracts\Browser` for testability
- [ ] `Clock` time provider implements `Contracts\Clock` for testability
- [ ] Startup sequence: read config → setup loop → create logger → init usage repo → init ingest → start TCP server → fetch initial usage → start composer.lock monitoring (if configured) → run loop
- [ ] Log levels configurable via `FLARE_DAEMON_LOG_LEVEL`: `critical`, `error`, `info`, `verbose`
- [ ] PHPStan passes at level max
- [ ] Feature tests for daemon lifecycle: startup, graceful shutdown

### US-011: Composer.lock update detection

**Description:** As a VPS operator, I want the daemon to detect when `composer.lock` changes (indicating a `composer update` was run), so that the daemon restarts with the updated binary.

**Acceptance Criteria:**
- [ ] `CheckForUpdates` class reads and hashes `composer.lock` on startup
- [ ] Re-reads and hashes every 60 seconds
- [ ] If hash changes: initiates graceful shutdown with 5-minute countdown, logging every minute
- [ ] After countdown: force-digest all buffers, wait for in-flight requests, stop loop
- [ ] If file temporarily unreadable (e.g., Envoyer symlink deploy): skips that check, does not trigger shutdown
- [ ] If no `FLARE_COMPOSER_LOCK` configured: update checking is disabled (Docker mode)
- [ ] PHPStan passes at level max
- [ ] Feature tests for update detection and graceful shutdown

### US-012: PHAR build

**Description:** As a release engineer, I want to build the daemon as a standalone PHAR binary with scoped namespaces, so that VPS users can run it without dependency conflicts.

**Acceptance Criteria:**
- [ ] `box.json.dist` configured: main `src/daemon.php`, output `build/daemon.phar`, PhpScoper compactor
- [ ] `scoper.inc.php` configures PHP-Scoper namespace isolation
- [ ] `build.sh` script: builds Docker build environment, installs production deps, compiles PHAR
- [ ] `docker/Dockerfile.build` provides the build environment image with box and PHP-Scoper
- [ ] Resulting `build/daemon.phar` is runnable with `php daemon.phar`
- [ ] PHAR starts the daemon and accepts TCP connections

### US-013: Docker image

**Description:** As a Docker user, I want a production-ready Docker image that runs the daemon, so that I can deploy it with minimal configuration.

**Acceptance Criteria:**
- [ ] `Dockerfile` based on `php:8.2-cli-alpine` with zlib extension
- [ ] Copies `build/daemon.phar` and `docker/entrypoint.sh` into image
- [ ] Exposes port 8787
- [ ] `docker/entrypoint.sh` starts the daemon
- [ ] Configurable via environment variables (`FLARE_API_KEY`, `FLARE_BASE_URL`, `FLARE_DAEMON_LISTEN`, `FLARE_DAEMON_LOG_LEVEL`)
- [ ] `docker run -d -e FLARE_API_KEY=key -p 8787:8787 spatie/flare-daemon` starts a working daemon

### US-014: Test infrastructure for the daemon

**Description:** As a developer working on the daemon, I want a test infrastructure with fakes and deterministic time, so that daemon behavior can be tested reliably and instantly.

**Acceptance Criteria:**
- [ ] `LoopFake` simulates time progression, tracks timers, runs scheduled callbacks deterministically
- [ ] `BrowserFake` records HTTP requests, returns pre-configured responses
- [ ] `TcpServerFake` simulates TCP connections with pending payloads
- [ ] `SyncedClock` provides deterministic clock synced with `LoopFake`'s time
- [ ] `Connection` asserts TCP connection responses
- [ ] `PendingConnection` prepares fake connection data
- [ ] `Request` asserts outbound HTTP requests
- [ ] `Response` creates fake HTTP responses (ingested, errors, quota)
- [ ] `Timer` asserts timer scheduling and execution
- [ ] `daemon-wrapper.php` and `bootstrap.php` for subprocess-based test runs
- [ ] Tests run deterministically (no real I/O or sleep) via `LoopFake` time simulation

## Functional Requirements

- FR-1: Add `FlareMode::Daemon` case to `src/Enums/FlareMode.php`
- FR-2: Add `FlareConfig::daemon(string $daemonUrl)` method that sets `DaemonSender` and stores the URL
- FR-3: `FlareProvider` resolves `FlareMode::Daemon` when `daemonUrl` is set, taking precedence over API key check
- FR-4: Add `FlareConfig::emergencyLogger(LoggerInterface $logger)` method
- FR-5: `Api::sendEntity()` calls emergency logger on delivery failure in non-test mode
- FR-6: `DaemonConnection` maintains a singleton persistent TCP socket with lazy open, reuse, auto-reconnect, and cleanup on destruct
- FR-7: `DaemonConnection::ping()` sends `PING`, returns `true`/`false`
- FR-8: `DaemonConnection::status()` sends `STATUS`, returns parsed JSON or `null`
- FR-9: `DaemonSender` implements `Sender` interface, formats payloads as `{length}:v1:{type}:{json}`
- FR-10: `DaemonSender` handles test payloads: appends `_test` to type, reads ack, then blocks for Flare response
- FR-11: `Tester::test()` orchestrates all three test types with progress callbacks (`onInfo`, `onWarning`, `onError`)
- FR-12: `Tester` handles daemon-specific flow: ack callback, timeout exception, two-phase reporting
- FR-13: Daemon TCP server accepts persistent connections on configurable address using ReactPHP
- FR-14: `Payload` parser supports length-prefixed protocol with chunked data, version validation, and sequential parsing on persistent connections
- FR-15: Server routes payloads by type to `Ingest`, responds `2:OK`, handles `PING` and `STATUS` commands
- FR-16: `Ingest` manages three `StreamBuffer` instances, one per `FlareEntityType`
- FR-17: Buffers flush when exceeding ~6MB threshold or after 10-second timer
- FR-18: Maximum 5 concurrent outbound HTTP requests shared across all types
- FR-19: Outbound requests use gzip compression with correct headers including `User-Agent: FlareDaemon/{version}`
- FR-20: Response handling: 201 success, 403 stop all, 422 log (stop on "Missing API key"), 429 rate limit backoff or quota pause, others logged
- FR-21: Test payloads bypass quota, trigger immediate flush, and return Flare response to client via `{length}:{type}:{statusCode}:{responseBody}`
- FR-22: `UsageRepository` fetches quota on startup, refreshes daily, tracks local counters, responds to 429s
- FR-23: Per-type quota independence — hitting one limit does not affect other types
- FR-24: Retry strategy: exponential backoff before first fetch, 300s interval after, 3600s after 13 failures
- FR-25: `CheckForUpdates` monitors `composer.lock` hash every 60s, triggers 5-minute graceful shutdown on change
- FR-26: Force digest on shutdown: flush all buffers, wait for in-flight requests
- FR-27: Daemon configured entirely via environment variables with sensible defaults
- FR-28: PHAR build via box-project/box with PHP-Scoper namespace isolation
- FR-29: Docker image based on `php:8.2-cli-alpine` with zlib, exposes port 8787

## Non-Goals

- Multiple API keys per daemon instance (one daemon = one key)
- HTTP-based client-to-daemon protocol (TCP only)
- Web dashboard for daemon monitoring
- Automatic restarts / process management (users should use systemd, supervisor, etc.)
- Payload persistence to disk (if daemon crashes, buffered data is lost)
- Client-side fallback (if daemon is down, client does not fall back to direct sending)

## Technical Considerations

- **ReactPHP stack:** `react/event-loop ^1.5`, `react/socket ^1.16`, `react/http ^1.11`, `react/promise ^3.2`, `react/stream ^1.4`
- **PHP 8.2+** required for both client and daemon
- **Daemon lives in `daemon/` directory** within the `flare-client-php` repository, with its own `composer.json` (`spatie/flare-daemon`)
- **Client changes** are in the existing `src/` directory of `flare-client-php`
- **PHPStan level max** for daemon code
- **Testing approach:** Daemon tests run via subprocess (`daemon-wrapper.php`) with serialized fakes and deterministic `LoopFake` time simulation
- **Namespace isolation:** PHP-Scoper ensures daemon dependencies don't conflict with the host application's dependencies when distributed as PHAR
- **Persistent connections:** Both client-to-daemon (TCP) and within the daemon's `Browser` for outbound HTTP should maintain persistent connections
- **Flare API responses** are always JSON: `{"message": "string", "errors": {}}`

## Success Metrics

- PHP application request latency is not impacted by Flare payload delivery (TCP write + ack is sub-millisecond on localhost)
- Daemon successfully buffers and batches payloads, reducing outbound HTTP requests compared to per-request sending
- Quota is respected per type — over-quota payloads are dropped locally, not rejected by Flare
- `flare:test` command verifies the full pipeline (client → daemon → Flare) with clear progress feedback
- Emergency logger surfaces delivery failures that were previously silent
- Daemon starts and runs reliably as both PHAR and Docker container

## Resolved Questions

- **TLS for client-to-daemon TCP?** No — out of scope for v1. Daemon is localhost-only; use a reverse proxy for remote access.
- **HTTP health check endpoint?** No — TCP `PING`/`STATUS` suffices for v1. Docker/K8s can use a small health check script that does a TCP `PING`.
- **`User-Agent` version format?** `FlareDaemon/{version}` pulled from `composer.json`'s `version` field.
- **`DaemonConnection` singleton resettable?** Yes — `static reset(): void` method that nulls `$instance` and closes the socket. Standard pattern for testable singletons.
