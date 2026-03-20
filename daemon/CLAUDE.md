# CLAUDE.md — daemon/

Independent package (`spatie/flare-daemon`), separate from the parent `flare-client-php`. Own composer.json, own autoload (`Spatie\FlareDaemon\`), own tests. Do not mix with the parent package.

## Commands

Run everything from the `daemon/` directory:

```bash
composer test                    # Pest tests
composer test --filter=ServerTest  # Single test file
composer analyse                 # PHPStan level 8
bash build.sh                    # Build daemon.phar
php src/daemon.php               # Run daemon locally (needs composer install first)
php src/daemon.php --verbose     # Run with per-request DEBUG logging
```

## Smoke-testing a build

```bash
bash build.sh
php daemon.phar &
curl -s http://127.0.0.1:8787/health   # should return {"status":"ok"}
kill %1
```

## Smoke-testing with a real API key

Start the daemon (with `--verbose` for full per-payload logs), then run `test.sh` with your Flare API key:

```bash
php src/daemon.php --verbose &
bash test.sh YOUR_API_KEY              # uses http://127.0.0.1:8787 by default
bash test.sh -u http://localhost:9000 YOUR_API_KEY  # custom daemon URL
kill %1
```

The script sends a normal error payload (no `X-Flare-Test` header) to exercise the real buffering/flushing path, then polls `/status` to confirm the buffer drained.

## Docker

```bash
docker build -t flare-daemon .
docker run -p 8787:8787 flare-daemon
```

## Testing patterns

- Tests use ReactPHP's event loop — everything is async. Use `waitFor()` / `waitUntil()` for timing, never `sleep()`.
- `createUpstreamFixture($handler)` spins up a fake upstream HTTP server. Returns `base_url` and `requests` ArrayObject.
- `createDaemonFixture($baseUrl, $options)` spins up a full daemon on an ephemeral port. Returns `daemon_url`, `client`, `ingest`, `server`, `quota_state`.
- Fixtures auto-clean via `rememberCloser()` / `rememberShutdown()` in afterEach — don't close them manually.
- Feature tests live in `tests/Feature/`, unit tests in `tests/Unit/`.

## Key design decisions

- Buffers are per API key × entity type (errors/traces/logs). Not a single shared queue.
- Test payloads (`X-Flare-Test: 1`) force an immediate flush and return the upstream response. Normal payloads return 202 immediately.
- 429 pauses that (key, type); 403 pauses all types for that key permanently. Normal items are dropped on pause, test items are kept.
- Upstream sends one payload per request (no batch API in v1).
- The errors CF worker is a transparent proxy — it passes through whatever status the real Flare API returns (currently 204). Traces/logs workers return a hardcoded 201. The daemon must treat any 2xx as success, not maintain an allowlist.

## After every code change

Run `composer test` and `composer analyse` from the `daemon/` directory after every code change. Both must pass before considering the change complete.

## Code style

Follow Spatie PHP guidelines: @~/.dotfiles/spatie-guidelines-claude.md
