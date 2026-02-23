# Flare Daemon - Product Requirements Document

## Overview

The Flare Daemon is a long-running PHP process that acts as a local proxy between Flare client applications and the
Flare ingestion service. Instead of each PHP request sending payloads directly to Flare (and waiting for the response),
clients send payloads to the daemon over a local TCP socket. The daemon buffers, batches, and forwards these payloads to
Flare asynchronously, reducing latency impact on application requests.

The daemon **owns the API key**. Client applications don't need a Flare API key - they only need the daemon's address.
This centralizes key management and simplifies client configuration.


## Architecture

```
┌─────────────┐     TCP      ┌─────────────┐     HTTPS       ┌─────────────┐
│  PHP App 1  │───────────>  │             │──────────────>   │             │
├─────────────┤  (fast,      │   Flare     │  /v1/errors     │   Flare     │
│  PHP App 2  │   local)     │   Daemon    │  /v1/traces     │   Service   │
├─────────────┤  no API key  │  (has key)  │  /v1/logs       │  (ingress)  │
│  PHP App N  │───────────>  │             │──────────────>   │             │
└─────────────┘              └─────────────┘                  └─────────────┘
                                   │
                                   │ GET /v1/usage (quota)
                                   ▼
```

### Key Design Decisions

| Decision                  | Choice                                                          |
|---------------------------|-----------------------------------------------------------------|
| Repository location       | Same repo, `daemon/` directory                                  |
| Package name              | `spatie/flare-daemon`                                           |
| Auth model                | Daemon owns the API key; clients have no key                    |
| Client mode               | New `FlareMode::Daemon` - enabled without API key               |
| Quota endpoint            | `GET /v1/usage` - dedicated endpoint                            |
| Client-to-daemon protocol | Raw TCP socket, persistent connections                          |
| Forwarding strategy       | Separate buffers + endpoints per type (errors, traces, logs)    |
| Payload format            | `length:version:type:jsonPayload` (no key hash)                 |
| Multi-project             | One daemon per API key                                          |
| Distribution              | PHAR + Docker image                                             |
| Default listen address    | `127.0.0.1:8787`                                                |
| Quota tracking            | Periodic fetch + local counters + response-driven stop          |
| Health check              | `PING` and `STATUS` via TCP, exposed through `DaemonConnection` |
| PHP version               | 8.2+                                                            |
| Update detection          | Watch `composer.lock` (VPS); not needed for Docker              |
| Daemon logging            | Stdout/stderr with levels via env var                           |

## Package Structure

```
flare-client-php/
├── src/
│   ├── Enums/
│   │   └── FlareMode.php             # MODIFIED: add Daemon case
│   ├── Senders/
│   │   └── DaemonSender.php          # NEW: TCP sender
│   ├── Support/
│   │   ├── Tester.php                # MODIFIED: test() orchestrator, CLI closures
│   │   └── DaemonConnection.php      # NEW: static TCP connection, ping, status
│   ├── Api.php                       # MODIFIED: emergency logger on send failure
│   ├── FlareConfig.php               # MODIFIED: add daemon() and emergencyLogger() methods
│   └── FlareProvider.php             # MODIFIED: FlareMode::Daemon support
│
├── daemon/
│   ├── src/
│   │   ├── daemon.php                # Entry point
│   │   ├── bootstrap.php             # Autoloader setup (PHAR-safe)
│   │   ├── Server.php                # TCP server accepting connections
│   │   ├── Payload.php               # TCP payload parser
│   │   ├── Ingest.php                # Buffering + forwarding per type
│   │   ├── UsageRepository.php       # Quota management via /v1/usage
│   │   ├── Usage.php                 # Usage data value object
│   │   ├── Loop.php                  # ReactPHP event loop wrapper
│   │   ├── StreamBuffer.php          # Per-type payload buffer
│   │   ├── NullBuffer.php            # No-op buffer (when over quota)
│   │   ├── Browser.php               # ReactPHP HTTP client wrapper
│   │   ├── CheckForUpdates.php       # Watch composer.lock for dependency changes
│   │   ├── OutputWriter.php          # Async-safe stdout/stderr writer
│   │   ├── Clock.php                 # Time provider
│   │   ├── Contracts/
│   │   │   ├── Browser.php
│   │   │   └── Clock.php
│   │   └── Factories/
│   │       └── BrowserFactory.php
│   │
│   ├── tests/
│   │   ├── Feature/
│   │   │   ├── DaemonTest.php
│   │   │   ├── IngestTest.php
│   │   │   ├── UsageRepositoryTest.php
│   │   │   └── ServerTest.php
│   │   ├── Unit/
│   │   │   ├── PayloadTest.php
│   │   │   └── StreamBufferTest.php
│   │   ├── TestCase.php
│   │   ├── BrowserFake.php
│   │   ├── LoopFake.php
│   │   ├── TcpServerFake.php
│   │   ├── SyncedClock.php
│   │   ├── Connection.php
│   │   ├── PendingConnection.php
│   │   ├── Request.php
│   │   ├── Response.php
│   │   ├── Timer.php
│   │   ├── daemon-wrapper.php
│   │   └── bootstrap.php
│   │
│   ├── composer.json
│   ├── phpstan.neon.dist
│   ├── phpunit.xml.dist
│   ├── box.json.dist                 # PHAR build config
│   ├── build.sh                      # Docker-based PHAR build
│   ├── scoper.inc.php                # PHP-Scoper namespace isolation
│   ├── Dockerfile                    # Production Docker image
│   ├── docker/
│   │   ├── Dockerfile.build          # Build environment image
│   │   ├── entrypoint.sh
│   │   └── install-composer.sh
│   ├── build/
│   │   └── daemon.phar
│   ├── .env.example
│   └── .gitignore
```

## Client-Side Changes

### 1. New FlareMode: Daemon

**File:** `src/Enums/FlareMode.php`

```php
enum FlareMode
{
    case Disabled;
    case Ignition;
    case Enabled;
    case Daemon;    // NEW
}
```

`FlareMode::Daemon` behaves identically to `Enabled` (tracing, logging, reporting all active) but does not require an
API key. The client sends payloads to the daemon's TCP socket instead of directly to Flare.

### 2. FlareConfig: daemon() Method

**File:** `src/FlareConfig.php`

New property and method:

```php
public ?string $daemonUrl = null;

public function daemon(string $daemonUrl = '127.0.0.1:8787'): static
{
    $this->daemonUrl = $daemonUrl;
    $this->sender = DaemonSender::class;
    $this->senderConfig = ['daemon_url' => $daemonUrl];

    return $this;
}
```

**Usage (framework-agnostic):**

```php
$flare = Flare::make(
    FlareConfig::make()
        ->daemon('127.0.0.1:8787')
        ->useDefaults()
);
```

No API key needed. The `FlareProvider` mode resolution changes to:

```php
$this->mode = match (true) {
    $this->config->daemonUrl !== null => FlareMode::Daemon,
    empty($this->config->apiToken) => FlareMode::Disabled,
    default => FlareMode::Enabled,
};
```

### 3. Emergency Logger

**File:** `src/FlareConfig.php`, `src/Api.php`

When a sender fails to deliver a payload in non-test mode, the error is currently silently swallowed
(`Api::sendEntity()` catches all exceptions and returns). This makes it impossible to diagnose delivery failures.

A new configurable emergency logger (`Psr\Log\LoggerInterface`) gives visibility into these failures:

```php
// FlareConfig
public ?LoggerInterface $emergencyLogger = null;

public function emergencyLogger(LoggerInterface $logger): static
{
    $this->emergencyLogger = $logger;

    return $this;
}
```

**Usage (framework-agnostic):**

```php
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

FlareConfig::make()
    ->emergencyLogger(new Logger('flare', [new StreamHandler('php://stderr')]))
```

**Usage (Laravel package):**

```php
FlareConfig::make()
    ->emergencyLogger(Log::channel('emergency'))
```

**Integration with `Api::sendEntity()`:**

The current silent `return` in the catch block changes to call the emergency logger if configured:

```php
} catch (Throwable $throwable) {
    if ($test) {
        throw $throwable;
    }

    $this->emergencyLogger?->error('Flare delivery failed', [
        'exception' => $throwable,
    ]);
}
```

This applies to **all** senders (CurlSender, DaemonSender, etc.) since the catch is in `Api`, not the sender.

### 4. Tester Changes

**File:** `src/Support/Tester.php`

The existing `Tester` class is updated to orchestrate the full test flow. It extracts the framework-agnostic parts of
the Laravel `flare:test` command — sending each payload type, handling success/failure, parsing error responses, and
providing support info. Framework-specific concerns (config checks, logger validation, output formatting with emojis and
colors) stay in the Laravel command.

**New constructor parameters:**

```php
public function __construct(
    protected Api $api,
    protected Sender $sender,
    protected Ids $ids,
    protected Time $time,
    protected Memory $memory,
    protected Resource $resource,
    protected ReportFactory $reportFactory,
    protected ?Closure $onInfo = null,
    protected ?Closure $onWarning = null,
    protected ?Closure $onError = null,
) {
}
```

| Parameter    | Description                                                                    |
|--------------|--------------------------------------------------------------------------------|
| `$sender`    | The configured sender — checked via `instanceof DaemonSender`                  |
| `$onInfo`    | `Closure(string): void` — progress messages (e.g., "Sending test error...")    |
| `$onWarning` | `Closure(string): void` — non-fatal issues (e.g., "Traces disabled, skipping") |
| `$onError`   | `Closure(string): void` — failures (e.g., "Error test failed: 422 ...")        |

**New `test()` method:**

Orchestrates sending all three test types and reporting results. Accepts which types are enabled (the caller decides
based on its own config — e.g., Laravel checks `config('flare.report')`, `config('flare.trace')`,
`config('flare.log')`).

```php
/** @param array<int, FlareEntityType> $types */
public function test(array $types, int $daemonTimeout = 30): bool
{
    $viaDaemon = $this->sender instanceof DaemonSender;
    $success = true;

    foreach (FlareEntityType::cases() as $type) {
        if (! in_array($type, $types)) {
            $this->warning("❌ {$type->singleName()} reporting is disabled.");
            continue;
        }

        $this->info("Sending test {$type->singleName()}...");

        if ($viaDaemon) {
            $this->info('Waiting for daemon response...');
        }

        $success = $this->sendTestPayload($type, $daemonTimeout) && $success;
    }

    return $success;
}
```

**`sendTestPayload()` method — regular flow:**

When using `CurlSender` (direct to Flare), the flow is synchronous:

```
🐛 Sending test error...
✅ Flare received test error successfully
```

Or on failure:

```
🐛 Sending test error...
❌ Failed sending test error: 422 - Invalid payload
```

**`sendTestPayload()` method — daemon flow:**

When using `DaemonSender`, there are two phases: sending to the daemon (TCP) and waiting for the Flare response. Each
phase can fail independently:

```
🐛 Sending test error to daemon...
➡️ Daemon received test error
⏳ Waiting for Flare response for test error...
✅ Flare received test error successfully
```

Failure sending to daemon (daemon unreachable):

```
🐛 Sending test error to daemon...
❌ Failed sending test error to daemon: Connection refused
```

Daemon received it but Flare rejected it:

```
🐛 Sending test error to daemon...
➡️ Daemon received test error
⏳ Waiting for Flare response for test error...
❌ Failed sending test error: 422 - Invalid payload
```

Daemon received it but timed out waiting for Flare response:

```
🐛 Sending test error to daemon...
➡️ Daemon received test error
⏳ Waiting for Flare response for test error...
❌ Timed out after 30s waiting for Flare response for test error
```

**Implementation:**

The `DaemonSender::post()` method is split into two steps for test payloads: writing the payload (which reads the `2:OK`
ack) and reading the Flare response. The `Tester` hooks into these steps via callbacks on the `DaemonSender`:

```php
protected function sendTestPayload(FlareEntityType $type, int $daemonTimeout): bool
{
    $name = $type->singleName();
    $viaDaemon = $this->sender instanceof DaemonSender;
    $emoji = match ($type) {
        FlareEntityType::Errors => '🐛',
        FlareEntityType::Traces => '🔍',
        FlareEntityType::Logs => '📝',
    };

    $this->info("{$emoji} Sending test {$name}" . ($viaDaemon ? ' to daemon...' : '...'));

    try {
        if ($viaDaemon) {
            $this->sender->onTestAck(function () use ($name) {
                $this->info("➡️ Daemon received test {$name}");
                $this->info("⏳ Waiting for Flare response for test {$name}...");
            });
        }

        match ($type) {
            FlareEntityType::Errors => $this->report(),
            FlareEntityType::Traces => $this->trace(),
            FlareEntityType::Logs => $this->log(),
        };

        $this->info("✅ Flare received test {$name} successfully");

        return true;
    } catch (DaemonTimeoutException $e) {
        $this->error("❌ Timed out after {$daemonTimeout}s waiting for Flare response for test {$name}");
        $this->reportSupportInfo();

        return false;
    } catch (BadResponseCode $e) {
        $message = 'Unknown error';

        if (is_array($e->response->body) && isset($e->response->body['message'])) {
            $message = $e->response->body['message'];
        }

        $this->error("❌ Failed sending test {$name}: {$e->response->code} - {$message}");
        $this->reportSupportInfo();

        return false;
    } catch (Throwable $e) {
        $this->error("❌ Failed sending test {$name}" . ($viaDaemon ? ' to daemon' : '') . ": {$e->getMessage()}");
        $this->reportSupportInfo();

        return false;
    }
}
```

The `DaemonSender::onTestAck()` method registers a one-time callback that fires after the daemon responds `2:OK` and
before the sender blocks to read the Flare response. This lets the Tester output the intermediate progress messages.

**`reportSupportInfo()` method:**

Outputs generic support info via `onInfo`. The Laravel command can supplement this with Laravel-specific details (Laravel
version, laravel-flare version, table formatting):

```php
protected function reportSupportInfo(): void
{
    $this->info('Make sure that your key is correct and that you have a valid subscription.');
    $this->info('For more info visit the docs on https://flareapp.io/docs');
    $this->info('You can see the status page of Flare at https://status.flareapp.io');
    $this->info('Flare support can be reached at support@flareapp.io');
}
```

**`platformInfo()` method:**

Returns framework-agnostic platform info. The Laravel command can merge in Laravel-specific info and render as a table:

```php
/** @return array<string, string> */
public function platformInfo(): array
{
    return [
        'Platform' => PHP_OS,
        'PHP' => phpversion(),
        $this->resource->telemetrySdkName => $this->resource->telemetrySdkVersion,
        'Curl' => curl_version()['version'] ?? 'Unknown',
        'SSL' => curl_version()['ssl_version'] ?? 'Unknown',
    ];
}
```

**Helper methods:**

```php
protected function info(string $message): void
{
    if ($this->onInfo) {
        ($this->onInfo)($message);
    }
}

protected function warning(string $message): void
{
    if ($this->onWarning) {
        ($this->onWarning)($message);
    }
}

protected function error(string $message): void
{
    if ($this->onError) {
        ($this->onError)($message);
    }
}
```

**Laravel command usage:**

After extracting, the Laravel `flare:test` command becomes much thinner — it handles config checks, logger validation,
and output formatting, then delegates to `Tester::test()`:

```php
// Laravel TestCommand::handle()
public function handle(Repository $config): int
{
    // Laravel-specific config checks...
    $this->checkFlareKey();
    $this->checkFlareLogger();

    $tester = app(Tester::class);

    $success = $tester->test(types: array_filter([
        $config['flare.report'] !== false ? FlareEntityType::Errors : null,
        $config['flare.trace'] !== false ? FlareEntityType::Traces : null,
        $config['flare.log'] !== false ? FlareEntityType::Logs : null,
    ]));

    if (! $success) {
        // Add Laravel-specific info to the table
        $info = array_merge($tester->platformInfo(), [
            'Laravel' => app()->version(),
            'spatie/laravel-flare' => InstalledVersions::getVersion('spatie/laravel-flare'),
        ]);
        $this->table([], collect($info)->map(fn ($v, $k) => [$k, $v])->values()->all());
    }

    return $success ? Command::SUCCESS : Command::FAILURE;
}
```

**Daemon timeout:**

When `viaDaemon` is `true`, the `DaemonSender` applies `stream_set_timeout($daemonTimeout)` on the socket before reading
the test response. If the read times out, a `DaemonTimeoutException` is thrown.

Returns `true` if all three types passed, `false` if any failed.

### 5. DaemonConnection

**File:** `src/Support/DaemonConnection.php`

Static class that owns the TCP socket to the daemon. Used by `DaemonSender` for sending payloads and directly by the
`Tester` / Laravel commands for status checks. The static design ensures a single persistent connection across the
entire PHP process.

```php
class DaemonConnection
{
    private static ?self $instance = null;

    /** @var resource|null */
    private $socket = null;

    private function __construct(
        private string $daemonUrl,
    ) {
    }

    public static function create(string $daemonUrl): self
    {
        return self::$instance ??= new self($daemonUrl);
    }

    public function write(string $data): void { /* ... */ }

    public function read(): string { /* ... */ }

    public function readWithTimeout(int $seconds): string { /* ... */ }

    public function ping(): bool { /* ... */ }

    public function status(): ?array { /* ... */ }

    public function close(): void { /* ... */ }

    public function __destruct()
    {
        $this->close();
    }
}
```

**Persistent connection lifecycle:**

A single TCP connection is kept open for the lifetime of the PHP process. This is important because:

- A single web request can produce logs, a trace, and an error — all sent through one connection
- Queue workers live ~15 minutes. `Api::sendQueue()` is called after every job (`endSubtask()` → `flush()`), producing
  multiple `Sender::post()` calls. A persistent connection avoids per-job TCP handshake overhead

| Behavior         | Description                                                                         |
|------------------|-------------------------------------------------------------------------------------|
| **Lazy open**    | Socket is created on first `write()`, `ping()`, or `status()` call                 |
| **Reuse**        | All subsequent calls reuse the same socket                                          |
| **Reconnect**    | If a write/read fails, the socket is closed and re-established on the next call     |
| **Cleanup**      | `__destruct` closes the socket when the PHP process ends                            |

**`ping()` method:**

Sends `PING` over the socket. Returns `true` if the daemon responds `2:OK`, `false` if unreachable.

**`status()` method:**

Sends `STATUS` over the socket. Returns the parsed JSON response, or `null` if unreachable:

```json
{
    "status": "running",
    "errors_used": 45000,
    "errors_limit": 50000,
    "errors_paused": false,
    "traces_used": 12000,
    "traces_limit": 100000,
    "traces_paused": false,
    "logs_used": 8000,
    "logs_limit": 50000,
    "logs_paused": false,
    "reset_at": "2026-03-01T00:00:00Z"
}
```

The Laravel test command can use `DaemonConnection` directly to show daemon state before running tests.

### 6. DaemonSender

**File:** `src/Senders/DaemonSender.php`

Implements the existing `Sender` interface. Uses `DaemonConnection` for all TCP communication.

**TCP Payload Format:**

```
{total_length}:{version}:{type}:{jsonPayload}
```

| Field          | Description                                                                            |
|----------------|----------------------------------------------------------------------------------------|
| `total_length` | Total byte length of the entire message (for completeness checking)                    |
| `version`      | Protocol version: `v1`                                                                 |
| `type`         | Payload type: `errors`, `traces`, `logs`, `errors_test`, `traces_test`, or `logs_test` |
| `jsonPayload`  | The JSON-encoded payload (same format as sent to Flare today)                          |

No API key hash is included. The daemon trusts connections on its localhost socket.

**Behavior (normal payloads):**

- Formats the payload and calls `DaemonConnection::write()`
- Reads the response (`2:OK` on success) via `DaemonConnection::read()`
- On failure (daemon unreachable): throws exception, caught by `Api::sendEntity` which logs via emergency logger

**Behavior (test payloads):**

When `Sender::post()` is called with `test: true`, the `DaemonSender` appends `_test` to the type (e.g., `errors`
becomes `errors_test`). The daemon acks immediately, then sends the Flare response later:

- Writes the formatted payload with the `_test` type suffix via `DaemonConnection::write()`
- Reads the ack (`2:OK`) via `DaemonConnection::read()`
- Then reads the **Flare response** via `DaemonConnection::readWithTimeout()` that arrives once the buffer flushes
  (format: `{length}:{type}:{statusCode}:{responseBody}`)
- Verifies the `type` in the response matches what was sent
- Parses the status code and body into a `Response` object
- Calls the response callback (same as `CurlSender` does)
- On failure (daemon unreachable): throws `ConnectionError` (test mode re-throws, matching existing `Api::sendEntity`
  behavior)

## Daemon Specifications

### 1. Entry Point (daemon.php)

Procedural entry point that wires everything together:

1. Read configuration from environment variables
2. Set up the ReactPHP event loop
3. Create logging helpers (debug, info, error)
4. Initialize usage repository (quota checking)
5. Initialize ingest system (3 type-specific buffers)
6. Start TCP server
7. Fetch initial usage data
8. Start `composer.lock` monitoring (if configured)
9. Run the event loop

### 2. TCP Server

**Class:** `Server`

- Listens on configured address (default `127.0.0.1:8787`)
- Accepts persistent TCP connections from clients (connections stay open for multiple payloads)
- Parses incoming `Payload` objects (supports chunked data)
- Validates payload version
- Routes payloads by type:
    - Normal types (`errors`, `traces`, `logs`): routes to `Ingest` buffer, responds `2:OK` via `$connection->write()`
      (not `end()` — connection stays open)
    - Test types (`errors_test`, `traces_test`, `logs_test`): responds `2:OK` immediately (same as normal), routes to
      `Ingest` buffer (bypassing quota), triggers immediate flush. When Flare responds, sends a second message on the
      same connection: `{length}:{type}:{statusCode}:{responseBody}` via `$connection->write()`
      (see [Test Payloads](#13-test-payloads))
- After each complete payload, the `Payload` parser resets to accept the next payload on the same connection
- Supports `PING` for health checks (responds `2:OK`, connection stays open)
- Supports `STATUS` command (responds with `{length}:{jsonStatusPayload}`, connection stays open)
- Handles `close` event gracefully when clients disconnect
- Logs connection errors

### 3. Payload Protocol

**Class:** `Payload`

Parses: `{length}:{version}:{type}:{data}`

- Accumulates chunks until `complete` (length matches)
- Validates protocol version (`v1`)
- Extracts type (`errors`, `traces`, `logs`, `errors_test`, `traces_test`, `logs_test`) for routing
- Exposes `isTest()` method (true when type ends in `_test`)
- Exposes `baseType()` method (strips `_test` suffix to get the `FlareEntityType` value)
- Rejects payloads with invalid version (triggers daemon shutdown for upgrade)
- Supports sequential parsing on persistent connections: after a payload is complete, the parser resets and any leftover
  bytes from the current chunk (beyond the current payload's length) are fed into the next payload parse

### 4. Ingest (Buffering & Forwarding)

**Class:** `Ingest`

Manages three separate `StreamBuffer` instances, one per `FlareEntityType`.

**Buffering strategy (per type):**

- Payload arrives -> append to the buffer for that type
- Buffer exceeds size threshold (~6MB) -> send immediately
- Buffer has data but under threshold -> send after 10 seconds
- Up to 5 concurrent outbound requests (shared across all types)

**Forwarding to Flare:**

| Type   | Endpoint                                |
|--------|-----------------------------------------|
| Errors | `POST {baseUrl}/v1/errors?key={apiKey}` |
| Traces | `POST {baseUrl}/v1/traces?key={apiKey}` |
| Logs   | `POST {baseUrl}/v1/logs?key={apiKey}`   |

Headers:

```
x-api-token: {apiKey}
Content-Type: application/json
Content-Encoding: gzip
Accept: application/json
User-Agent: FlareDaemon/{version}
```

Payloads are gzip-compressed before sending.

**Response handling:**

- Success (2xx): log, continue
- Quota exceeded (429 or `{stop: true}` in body): pause that type's buffer, notify `UsageRepository`
- Error (other): log, continue
- Response body >1005 bytes: truncate in logs

**Force digest:**
On shutdown, flush all buffers and wait for all in-flight requests to complete before stopping.

**Pause/resume:**
When a type is over quota, its `StreamBuffer` is swapped to a `NullBuffer` (drops incoming payloads). On resume, the
real buffer is restored.

**Test payload handling:**
Test payloads (`errors_test`, `traces_test`, `logs_test`) go through the same buffering pipeline as normal payloads but
with two differences: they bypass quota checks and they trigger an immediate buffer flush.

Flow:

1. `Ingest` receives a test payload and identifies it via `Payload::isTest()`
2. The server responds `2:OK` immediately (same as normal payloads)
3. The payload is written to the **real** `StreamBuffer` for that base type — even if the type is over quota and the
   active buffer is a `NullBuffer`, the test payload bypasses it and writes to the underlying `StreamBuffer` directly
4. The TCP connection reference is stored as a pending test connection for that type
5. An immediate flush of that type's buffer is triggered (no waiting for the 10-second timer or size threshold)
6. The flush sends the batch to Flare (the test payload may be batched with other buffered payloads of the same type)
7. When the Flare response arrives, a second message is sent to all pending test connections for that type via
   `$connection->write()` (connection stays open)
8. Test payloads do **not** count toward local usage counters

The `Ingest` class maintains a reference to the real `StreamBuffer` for each type even when swapped to `NullBuffer`, so
test payloads can always reach it.

**TCP response format for test payloads:**

```
{length}:{type}:{statusCode}:{responseBody}
```

| Field          | Description                                                              |
|----------------|--------------------------------------------------------------------------|
| `length`       | Byte length of `{type}:{statusCode}:{responseBody}`                      |
| `type`         | The base type: `errors`, `traces`, or `logs`                             |
| `statusCode`   | HTTP status code from Flare (e.g., `200`, `422`, `404`)                  |
| `responseBody` | Raw JSON response body from Flare                                        |

Example: `32:errors:200:{"message":"success"}` — 32 is the byte length of `errors:200:{"message":"success"}`.

Including the type in the response makes each response self-identifying. While the synchronous request-response pattern
on the persistent connection guarantees ordering, the type makes matching explicit and aids debugging.

### 5. Quota Management

**Class:** `UsageRepository`

**Value object:** `Usage`

```php
class Usage
{
    public function __construct(
        public int $errorsUsed,
        public int $errorsLimit,
        public int $tracesUsed,
        public int $tracesLimit,
        public int $logsUsed,
        public int $logsLimit,
        public string $resetAt,
    ) {}
}
```

**Behavior:**

1. **On startup:** Fetch `GET {baseUrl}/v1/usage?key={apiKey}` to get current quota state. If any type is already over
   limit, immediately pause that type.

2. **Daily refresh:** Re-fetch usage every 24 hours via periodic timer to prevent stale data.

3. **Local tracking:** After each successful ingest, increment local counters. The daemon knows how many items are in
   each payload (array count). When a local counter reaches the limit, pause that type.

4. **Response-driven:** If Flare responds with 429 or body contains `{stop: true}`, immediately pause that type and
   schedule a usage re-fetch.

5. **Per-type quota:** Each type (errors, traces, logs) is independent. Hitting the error limit doesn't pause traces or
   logs.

6. **Resume logic:** When usage is re-fetched and a type is under quota, resume ingestion for that type via
   `Ingest::resumeIngestion($type)`.

7. **Reset date handling:** If all types are over quota, schedule a re-fetch at `reset_at` time.

**Expected quota API response:**

```json
{
    "errors_used" : 45000,
    "errors_limit" : 50000,
    "traces_used" : 12000,
    "traces_limit" : 100000,
    "logs_used" : 8000,
    "logs_limit" : 50000,
    "reset_at" : "2026-03-01T00:00:00Z"
}
```

**Retry strategy (on fetch failure):**

- Before first successful fetch: exponential backoff (2.5s, 5s, 10s, 15s, 30s, 60s, 120s, 240s, then 300s x12, then
  3600s)
- After first successful fetch: retry every 300s, then 3600s after 13 consecutive failures

### 6. Update Detection

**Class:** `CheckForUpdates`

Detects when the application's dependencies have changed (e.g., `composer update` was run) so the daemon can restart
with the updated binary. Only relevant for VPS deployments where the daemon PHAR lives inside the vendor directory.
Docker deployments don't need this — pulling a new image and restarting the container handles updates.

**Behavior:**

- On startup: read and hash the `composer.lock` file at the configured path
- Every 60 seconds: re-read and hash the file
- If the hash changes (`composer update` was run): initiate graceful shutdown
    - 5-minute countdown with logging every minute
    - After countdown: force-digest all buffers, wait for in-flight requests, stop loop
- If the file is temporarily unreadable (e.g., during an Envoyer symlink deploy): skip that check, don't trigger
  shutdown
- If no `composer.lock` path is configured: update checking is disabled (Docker mode)

**Configuration:**

The path to `composer.lock` is set via environment variable (see [Configuration](#7-configuration)).

### 7. Configuration

All via environment variables:

| Variable                 | Default                       | Description                                        |
|--------------------------|-------------------------------|----------------------------------------------------|
| `FLARE_API_KEY`          | *(required)*                  | The Flare project API key                          |
| `FLARE_BASE_URL`         | `https://ingress.flareapp.io` | Flare ingestion base URL                           |
| `FLARE_DAEMON_LISTEN`    | `127.0.0.1:8787`              | TCP listen address:port                            |
| `FLARE_DAEMON_LOG_LEVEL` | `info`                        | One of: `critical`, `error`, `info`, `verbose`     |
| `FLARE_COMPOSER_LOCK`    | *(none)*                      | Path to `composer.lock` for update detection (VPS) |

### 8. Event Loop & Infrastructure

**ReactPHP stack:**

- `react/event-loop ^1.5` - StreamSelectLoop
- `react/socket ^1.16` - TCP server
- `react/http ^1.11` - HTTP client (Browser) for outbound requests
- `react/promise ^3.2` - Promise-based async
- `react/stream ^1.4` - Async stream writing (for logging)

**Loop wrapper:** Custom `Loop` class wrapping `LoopInterface` to track `running()` state. Needed for `OutputWriter` to
choose sync vs async writing.

**Output writer:** Uses async `WritableResourceStream` when the loop is running, falls back to sync `fwrite` when it's
not.

**Browser wrapper:** Thin wrapper around `React\Http\Browser` implementing `Contracts\Browser` for testability.

### 9. PHAR Build

**Build tool:** [box-project/box](https://github.com/box-project/box) with PHP-Scoper for namespace isolation.

**box.json.dist:**

```json
{
    "main" : "src/daemon.php",
    "output" : "build/daemon.phar",
    "alias" : "flare-daemon.phar",
    "check-requirements" : false,
    "banner" : false,
    "shebang" : false,
    "compactors" : [
        "KevinGH\\Box\\Compactor\\PhpScoper"
    ]
}
```

**build.sh:**

```bash
#!/usr/bin/env bash
set -e
docker build --platform=linux/amd64 -t flare-daemon-builder -f docker/Dockerfile.build .
docker run --rm -v $(pwd):/app --entrypoint composer flare-daemon-builder \
    install --prefer-dist --no-dev --no-interaction --classmap-authoritative
docker run --rm -v $(pwd):/app flare-daemon-builder
```

### 10. Docker Image

**Dockerfile** (production):

```dockerfile
FROM php:8.2-cli-alpine

RUN apk add --no-cache zlib-dev && docker-php-ext-install zlib

COPY build/daemon.phar /opt/flare/daemon.phar
COPY docker/entrypoint.sh /opt/flare/entrypoint.sh

RUN chmod +x /opt/flare/entrypoint.sh

EXPOSE 8787

ENTRYPOINT ["/opt/flare/entrypoint.sh"]
```

**Usage:**

```bash
docker run -d \
    -e FLARE_API_KEY=your-key-here \
    -p 8787:8787 \
    spatie/flare-daemon
```

### 11. Static Analysis

PHPStan at `level: max`:

```neon
parameters:
    paths:
        - src
        - tests
    level: max
```

### 12. Testing

**Framework:** PHPUnit

**Fakes/doubles**

| Fake                | Purpose                                                                               |
|---------------------|---------------------------------------------------------------------------------------|
| `LoopFake`          | Simulates time progression, tracks timers, runs scheduled callbacks deterministically |
| `BrowserFake`       | Records HTTP requests, returns pre-configured responses                               |
| `TcpServerFake`     | Simulates TCP connections with pending payloads                                       |
| `SyncedClock`       | Deterministic clock synced with LoopFake's time                                       |
| `Connection`        | Asserts TCP connection responses                                                      |
| `PendingConnection` | Prepares fake connection data                                                         |
| `Request`           | Asserts outbound HTTP requests                                                        |
| `Response`          | Creates fake HTTP responses (ingested, errors, quota)                                 |
| `Timer`             | Asserts timer scheduling and execution                                                |

**Test approach:**
Tests run the daemon in a subprocess via `daemon-wrapper.php`. Test configuration (fakes, loop, browsers) is serialized
to a file, the daemon reads it on boot, and writes updated state back. The `LoopFake` simulates time progression so
tests run instantly.

**Test categories:**

1. **Unit tests:**
    - `PayloadTest` - TCP payload parsing, chunked data, validation, type extraction, `isTest()`, `baseType()`
    - `StreamBufferTest` - Threshold, pull, flush, per-type isolation

2. **Feature tests:**
    - `DaemonTest` - Full lifecycle: startup, signature change, graceful shutdown
    - `IngestTest` - Buffering per type, batching, concurrent requests, quota stop/resume, error handling, force digest
      on shutdown, test payload buffering and response forwarding
    - `UsageRepositoryTest` - Quota fetch on startup, daily refresh, per-type pause/resume, local counter tracking,
      response-driven stop, retry strategies
    - `ServerTest` - TCP connection handling, PING, invalid payloads, incomplete payloads, version mismatch, persistent
      connections (multiple payloads on same connection), test payload response format
    - `TestPayloadTest` - Test payloads buffered and trigger immediate flush, response per type with type identification,
      bypass quota (write to real buffer when NullBuffer active), upstream error returns synthetic 503, force digest
      delivers test response, multiple test types on same persistent connection

### 13. Test Payloads

The Flare client includes a `Tester` class that sends test payloads to verify integration. Today with `CurlSender`, the
test payload is sent synchronously to Flare and the response (status code + body) determines success/failure. Exceptions
are re-thrown in test mode so the user sees what went wrong.

In daemon mode, the same mechanism works end-to-end. The `Tester` calls `report()`, `trace()`, and `log()` sequentially,
each resulting in a `DaemonSender::post()` call over the same persistent connection.

**Client side (`DaemonSender`):**

1. `Sender::post()` is called with `test: true`
2. `DaemonSender` appends `_test` to the type (e.g., `errors` → `errors_test`)
3. Writes the payload over the persistent TCP connection: `{length}:v1:errors_test:{jsonPayload}`
4. Reads the ack (`2:OK`) — confirms the daemon received it
5. Blocks and reads the **Flare response** that arrives later: `{length}:{type}:{statusCode}:{responseBody}`
6. Verifies the `type` in the response matches what was sent
7. Parses the status code and body into a `Response` object
8. Calls the response callback (same as `CurlSender` does — triggers `InvalidData`, `NotFound`, or `BadResponseCode`
   exceptions as appropriate)
9. Connection stays open for subsequent payloads (e.g., the next test type)
10. On failure (daemon unreachable or timeout): throws `ConnectionError`

**Daemon side:**

1. Server receives a `_test` payload and identifies it via `Payload::isTest()`
2. Responds `2:OK` immediately (same as normal payloads)
3. The payload is written to the real `StreamBuffer` for that base type (bypassing `NullBuffer` if over quota)
4. An immediate flush of that type's buffer is triggered
5. The TCP connection reference is stored as a pending test connection for that type
6. The flush sends the batch to Flare (may include other buffered payloads of the same type)
7. When Flare responds, the daemon sends a second message to all pending test connections for that type:
   `{length}:{type}:{statusCode}:{responseBody}` via `$connection->write()`
8. The connection stays open for more payloads

**Edge cases:**

- If the Flare request fails (network error), pending test connections receive a synthetic
  `{length}:{type}:503:{"message":"Upstream error"}`
- If the daemon shuts down while a test connection is pending, the force-digest flushes the buffer and delivers the
  response before closing

## Dependencies

**daemon/composer.json:**

```json
{
    "name" : "spatie/flare-daemon",
    "version" : "0.0.0",
    "type" : "project",
    "description" : "The official Flare daemon.",
    "license" : "MIT",
    "require" : {
        "php" : "^8.2",
        "ext-zlib" : "*",
        "psr/http-message" : "^1.1",
        "react/event-loop" : "^1.5",
        "react/http" : "^1.11",
        "react/promise" : "^3.2",
        "react/socket" : "^1.16",
        "react/stream" : "^1.4"
    },
    "require-dev" : {
        "ext-pcntl" : "*",
        "evenement/evenement" : "^3.0",
        "phpstan/phpstan" : "^2.1",
        "phpunit/phpunit" : "^11.5",
        "symfony/process" : "^7.2"
    },
    "autoload" : {
        "psr-4" : {
            "Spatie\\FlareDaemon\\" : "src/"
        }
    },
    "autoload-dev" : {
        "psr-4" : {
            "Tests\\" : "tests/"
        }
    },
    "config" : {
        "classmap-authoritative" : true,
        "sort-packages" : true
    }
}
```

## Out of Scope (v1)

- Multiple API keys per daemon instance
- HTTP-based client-to-daemon protocol (TCP only)
- Web dashboard for daemon monitoring
- Automatic restarts / process management (users should use systemd, supervisor, etc.)
- Payload persistence to disk (if daemon crashes, buffered data is lost)
- Client-side fallback (if daemon is down, client doesn't fall back to direct sending)
