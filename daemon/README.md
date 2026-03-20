# Flare Daemon

A long-running PHP process that accepts error reports, traces, and logs from your application over a local HTTP connection and forwards them to [Flare](https://flareapp.io) asynchronously. This removes Flare delivery from the critical path of your requests.

## How it works

```
PHP app ──HTTP──▸ daemon (local) ──HTTP──▸ Flare ingress
```

Payloads are buffered per API key and entity type and flushed immediately after being accepted. If the daemon is unreachable, the Flare client falls back to direct delivery automatically.

## Inside the process

The daemon is a single PHP process built on ReactPHP's event loop:

- **HTTP server** — listens for local requests on the configured address
- **Ingest** — validates incoming payloads, routes them to the right buffer
- **Buffers** — per API key, per entity type (errors/traces/logs), in-memory only
- **Flush cycle** — a periodic timer (every 1s) checks buffer age and size thresholds
- **Upstream** — sends buffered payloads to Flare ingress over HTTP
- **Quota state** — tracks 429/403 responses and pauses ingestion per key/type
- **Test payloads** — bypass the buffer entirely, make a synchronous upstream request, and return the upstream response to the caller
- **Composer.lock watcher** — optional periodic timer that triggers graceful shutdown on file changes
- **Signal handlers** — SIGINT/SIGTERM trigger graceful shutdown (drain buffers, then stop)

### Buffering

- Each unique combination of API key + entity type (errors, traces, logs) gets its own in-memory buffer
- When a payload arrives, the daemon validates it, drops it into the right buffer, and immediately returns `202 Accepted` with a JSON body to the caller
- A buffer is flushed immediately after a payload is accepted — there is no batch API yet, so buffering just adds latency
- A maintenance timer acts as a safety net, checking size and time thresholds every second (default 256 KB / 10 seconds)
- The daemon also flushes all buffers on shutdown
- Each flush sends one payload per upstream request — there is no batch API yet, but all upstream request building is isolated in `Upstream.php` so it can be swapped to batch sending later without changing the rest of the system
- Empty buffers are cleaned up automatically to avoid leaking memory
- Test payloads (flagged with `X-Flare-Test: 1`) skip the buffer entirely — they go straight to Flare and the daemon returns the upstream status, body, and selected headers directly
- If a key/type is paused due to a `429` or `403` from Flare, normal payloads for that key/type are silently dropped — test payloads are still allowed through

### Responses

Normal payloads return JSON `202 Accepted`:

```json
{
  "status": "accepted"
}
```

This keeps the daemon compatible with older curl-style Flare senders that expect a JSON response body.

Test payloads do not use direct fallback. They are intended to verify the daemon path itself.

When the daemon reaches Flare for a test payload, it returns the upstream response directly. For example, an upstream
`429` becomes an HTTP `429` response from the daemon with the same body and useful forwarded headers like
`Retry-After: 60`.

For example:

```json
"Trace quota exceeded"
```

If the daemon cannot reach Flare for the diagnostic request, it returns an error from the daemon itself such as `502`.

## Running the daemon

### Docker

```bash
docker run -d --name flare-daemon -p 8787:8787 spatie/flare-daemon
```

### PHAR

```bash
php daemon.phar
```

## Verbose mode

By default the daemon logs lifecycle events (started, stopped) and a periodic summary of forwarded payloads. Pass `--verbose` (or `-v`) to also log every individual payload at `DEBUG` level:

```bash
php daemon.phar --verbose
```

```bash
docker run -d --name flare-daemon -p 8787:8787 spatie/flare-daemon --verbose
```

## Configuration

All configuration is done through environment variables:

| Variable | Default | Description |
|---|---|---|
| `FLARE_DAEMON_LISTEN` | `127.0.0.1:8787` | Address to listen on |
| `FLARE_DAEMON_UPSTREAM` | `https://ingress.flareapp.io` | Flare ingress URL |
| `FLARE_DAEMON_BUFFER_BYTES` | `262144` (256 KB) | Size threshold per buffer (used by maintenance safety net) |
| `FLARE_DAEMON_FLUSH_AFTER` | `10` | Seconds before maintenance flushes oldest buffered items (safety net) |
| `FLARE_DAEMON_UPSTREAM_TIMEOUT` | `10` | Timeout in seconds for upstream requests |
| `FLARE_COMPOSER_LOCK` | _(none)_ | Path to `composer.lock` — daemon stops when the file changes |

## Smoke-testing with a real API key

Start the daemon, then use `test.sh` to send a real error payload through the full buffering/flushing path:

```bash
php src/daemon.php --verbose &
bash test.sh YOUR_API_KEY
```

The script checks `/health`, sends a normal error to `/v1/errors`, and polls `/status` until the buffer drains. Pass `-u URL` to target a different daemon address.

## Building

```bash
bash build.sh
```

This downloads [Box](https://github.com/box-project/box) (if needed) and compiles the PHAR.

## Development

```bash
composer install
composer test      # Run tests
composer analyse   # PHPStan (level 8)
```

## License

The MIT License (MIT). Please see [License File](../LICENSE.md) for more information.
