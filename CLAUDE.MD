# CLAUDE.md

This file provides guidance to Claude Code when working with this repository.

## Project Overview

PHP package that sends errors, traces, and logs to [Flare](https://flareapp.io). This is the core client library used by `spatie/laravel-flare` and other Flare integrations. Framework-agnostic — do not add Laravel-specific code here.

## Architecture

### Source Structure

- `src/` - Main package code (`Spatie\FlareClient\`)
  - `Flare.php` - Main entry point
  - `Api.php` - HTTP client for communicating with Flare
  - `Report.php` / `ReportFactory.php` - Error report building
  - `Logger.php` - Log recording and flushing (OpenTelemetry log format)
  - `Tracer.php` / `Spans/` - Performance tracing and spans
  - `Senders/` - Transport layer (CurlSender, DaemonSender)
  - `Senders/Exceptions/` - Sender exceptions (ConnectionError, BadResponseCode, etc.)
  - `Recorders/` - Data recorders (queries, logs, dumps, etc.)
  - `FlareMiddleware/` - Middleware pipeline for enriching reports
  - `Sampling/` - Sampling strategies for performance monitoring
  - `Truncation/` - Report truncation to stay within size limits
  - `Resources/` - Resource definitions
  - `Exporters/` - Data export formats
  - `Scopes/` - Scope management
  - `Contracts/` - Interfaces
  - `Enums/` - Enumerations
  - `Support/` - Helper utilities (including `DaemonConnection`, `StderrLogger`, `Tester`)

### Daemon (`daemon/`)

A separate package (`spatie/flare-daemon`) lives in the `daemon/` directory. It's a long-running local HTTP daemon for asynchronous Flare delivery. Completely independent — own `composer.json`, own namespace (`Spatie\FlareDaemon\`), own tests. See `daemon/PRD.md` for full specifications.

Important daemon facts:

- The daemon is a transport choice, not a new `FlareMode`.
- Client apps opt in via `FlareConfig::daemon()`.
- Normal payload path is `PHP app -> local daemon -> Flare ingress`.
- If the daemon fails, the client falls back to direct delivery; direct upstream failures are intentionally silent on normal non-test sends.
- Test payloads should validate the real daemon path and should not silently fall back.
- The daemon is in-memory only in v1: no disk persistence, no finalized batch contract yet, and one upstream request per payload.
- The daemon package is intended to ship as both PHAR and Docker.

### Tech Stack

- **PHP**: ^8.2
- **HTTP**: Guzzle 7
- **Logging**: psr/log ^3.0 (used for emergency logging)
- **Testing**: Pest with snapshot testing (`spatie/pest-plugin-snapshots`)
- **Static Analysis**: PHPStan level 8
- **Code Style**: php-cs-fixer

## Commands

```bash
composer test          # Pest tests
composer analyse       # PHPStan level 8
composer format        # php-cs-fixer (with --allow-risky=yes)
cd daemon && composer test
cd daemon && vendor/bin/phpstan analyse
cd daemon && bash ./build.sh
```

## Development Guidelines

- Follow existing patterns - check sibling files before creating new ones
- Don't create new base folders or change dependencies without approval
- This is a framework-agnostic PHP package — no Laravel-specific code
- The `daemon/` package is fully independent: own autoload, own dependencies, own tests

### Testing

- Uses Pest (`composer test`)
- Snapshot tests in `tests/__snapshots__/`
- Shared test utilities (used by both client and daemon) in `shared/`
- Run minimal tests with `--filter` during development

### Code Quality

- Run `composer format` before finalizing PHP changes
- Every change must have tests

## Code Style

Follow Spatie PHP and Laravel guidelines: @~/.dotfiles/spatie-guidelines-claude.md
