# Changelog

All notable changes to `flare-client-php` will be documented in this file

## 3.6.0 - 2026-11-06

Nested body censoring, cookie censoring by name, cache and database connection events, and a fix for forked processes resending the parent's data.

### Censor body fields without dot notation at any depth (#105)

A body field like `password` was only censored at the top level, so `{"user": {"password": "..."}}` reached Flare as-is. A field name without a dot now matches that key at any depth, so the default `password` and `password_confirmation` fields also cover nested JSON bodies. Dot notation paths like `user.password` keep matching only that exact path, and a bare `*` still censors every top-level field.

Existing installs will see more censoring after upgrading: a configured name like `email` now also hides `address.email` and `items.*.email`. Use a dotted path if you want one exact key.

Thanks @mattiasgeniar.

### Censor cookies by name (#110)

Cookies could only be censored all or nothing. Either Flare got every cookie, or `censorCookies()` dropped them all. You can now censor specific cookies by name, next to the existing header and body field lists:

```php
FlareConfig::make('api-key')
    ->censorCookieNames('PHPSESSID', 'remember_*');

```
Censored cookies keep their key, so you still see they were there. Names match case insensitively, a dot in a cookie name is taken literally, and a segment ending in `*` matches every key with that prefix. Body fields get the prefix wildcard too, for example `censorBodyFields('pass*', 'user.api_*')`.

Thanks @rubenvanassche.

### Censor cookie names inside the `Cookie` and `Set-Cookie` headers (#114)

Cookie names were only censored in the parsed `http.request.cookies` array. When the `Cookie` or `Set-Cookie` headers were removed from `censorHeaders()`, the raw headers still carried the values. The redactor now censors matching `name=value` pairs inside both headers as well.

The Symfony request and response providers also joined multi-value headers without a separator, which glued a Laravel response's two `Set-Cookie` lines into one string. They now join with a comma.

Thanks @rubenvanassche.

### Stop forked child processes from resending the parent's data (#108)

After `pcntl_fork()` the child got a copy of the open trace, the buffered logs and the queued reports, and sent all of it again on exit. Fork three children and Flare received the same spans four times (#99).

A child now behaves like a separate service continuing the parent's trace. It keeps the trace id, uses the span that was open at the fork as its parent, and only sends what it created itself. The new `ForkedProcess` class detects the fork right before anything is buffered or sent, and `Tracer`, `Logger` and `Api` reset their buffers when it fires. `Api`, `Tracer` and `Logger` gained a `ForkedProcess` constructor argument, which only matters if you construct them yourself.

Thanks @rubenvanassche and @tareq-halaby.

### Record failed cache writes and forgets (#97), flushes and tags (#98)

The `CacheRecorder` only recorded successful operations. It now has `recordKeyWriteFailed()` and `recordKeyForgetFailed()` for a store that refuses writes, and `recordFlushed()` and `recordFlushFailed()` for a cache being wiped. `Flush` is part of the default operations, since a flush is rare and high signal.

Every recording method also accepts `array $tags = []`, which lands in a `cache.tags` attribute. A tagged flush only clears part of a store, so without the tags it would look like a full flush. The failover event that #97 introduced was removed again in #115 before this release.

Thanks @rubenvanassche.

### Record database connection spans (#111)

A new `DatabaseConnectionRecorder` records a "Database connection" span around the moment a connection is opened, with the `php_database_connection` span type. Integrations call `recordStart()`, `recordEnd()` and `recordFailed()` around their own connect call. The recorder is registered with the query recorder and follows its `with_traces` setting, and connection spans only show up in traces.

Thanks @rubenvanassche.

### Keep report trimming memory bounded for very large payloads (#102)

`ReportTrimmer::needsToBeTrimmed()` encoded the whole payload with `json_encode` before every trimming strategy. A report with a big request body could push the process past its memory limit while reporting an error, and the report was lost.

The trimmer now first sums the length of all strings in the payload. JSON encoding never makes a string shorter, so when that sum is already over the limit it skips the encode and lets the strategies cut the large strings first. Trimming a payload with a 50 MB string used about 50 MB of extra memory before. It now stays small.

Thanks @AlexVanderbist.

### Rethrow uncaught exceptions when there is no previous exception handler (#106)

When Flare's exception handler was the only one registered, it reported the exception and then swallowed it. PHP never saw it as uncaught, so the request ended with an empty `200`, nothing reached the error log, and `error_get_last()` stayed empty. That broke apps like WordPress, which show their recovery page from a shutdown function.

`handleException()` now rethrows the exception when there is no previous handler, so PHP returns a `500` and logs the error. The exception is still reported only once.

Thanks @AlexVanderbist.

### Add `recordEndFromGlobals` to record request attributes in plain PHP apps (#107)

Plain PHP apps that ended a request with `recordEndFromDefined()` never got the request attributes such as `http.request.method` and `url.full`, so Flare could not group those requests into routes. `recordEndFromGlobals()` is the counterpart of `recordStartFromGlobals()` and reads the request from `$_SERVER` and the response from `http_response_code()` and `headers_list()`.

`PhpResponseAttributesProvider` now falls back to those globals when no status code or headers are passed, so `recordEndFromDefined()` picks them up as well.

Thanks @AlexVanderbist.

### Fix `max_items_with_errors` configuration for view collection (#103)

The view recorder ignored the `max_items_with_errors` option, so an error report could carry every rendered view (#101). `collectViews()` now has a `maxItemsWithErrors` parameter, defaulting to 100 like the other recorders, and forwards it to the recorder.

The new parameter sits before `$extra`. A call that passed the extra options array as the third positional argument must switch to a named argument.

Thanks @Menna-Baligh.

### Handle omitted fields when mapping OpenTelemetry attributes to PHP (#113)

OTLP/JSON leaves out empty and default fields, so a valid client can send an attribute without a `value` or `key`, or an empty list as `{"arrayValue": {}}`. The mapper read those keys directly and threw a `TypeError` or raised an undefined key warning. Those fields now fall back to a default: an attribute without a value is skipped, one without a key is stored under an empty string, and an empty list becomes an empty array.

Thanks @rubenvanassche.

### What's Changed

* Record failed cache writes, forgets and store failovers by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/97
* Record cache flushes and cache tags by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/98
* Keep report trimming memory bounded for very large payloads by @AlexVanderbist in https://github.com/spatie/flare-client-php/pull/102
* Fix max_items_with_errors configuration for view collection by @Menna-Baligh in https://github.com/spatie/flare-client-php/pull/103
* Censor body fields without dot notation at any depth by @mattiasgeniar in https://github.com/spatie/flare-client-php/pull/105
* Rethrow uncaught exceptions when there is no previous exception handler by @AlexVanderbist in https://github.com/spatie/flare-client-php/pull/106
* Add recordEndFromGlobals to record request attributes in plain PHP apps by @AlexVanderbist in https://github.com/spatie/flare-client-php/pull/107
* Stop forked child processes from resending the parent's data by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/108
* Censor cookies by name by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/110
* Record database connection spans by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/111
* Support PHP 8.6 by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/112
* Handle omitted fields when mapping OpenTelemetry attributes to PHP by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/113
* Censor cookie names inside the Cookie and Set-Cookie headers by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/114
* Drop the cache failover span event by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/115

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/3.5.1...3.6.0

## 1.11.2 - 2026-11-04

- Strip embedded credentials (for example a GitHub OAuth token from Laravel Forge deployments) from the git remote URL before it is sent to Flare, by @marventhieme in https://github.com/spatie/flare-client-php/pull/100

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.11.1...1.11.2

## 3.5.1 - 2026-09-09

### What's Changed

* Send the pretty package version as the telemetry SDK version by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/96

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/3.5.0...3.5.1

## 3.5.0 - 2026-09-08

### What's Changed

* Fix external HTTP spans leaking and missing error information by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/94
* Let job and queue sampling rules decide per job by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/95

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/3.4.4...3.5.0

## 3.4.4 - 2026-09-07

### What's Changed

* Dump the Flare configuration when running the tester in debug verbosity by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/93

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/3.4.3...3.4.4

## 3.4.3 - 2026-09-03

### What's Changed

* Check whether an entity is enabled before running its pre-checks in flare:test by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/92

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/3.4.2...3.4.3

## 3.4.2 - 2026-09-02

### What's Changed

* Always end the trace when a subtask ends by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/91

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/3.4.0...3.4.2

## 3.4.1 - 2026-08-27

Rerelease due to tagging conflict

## 3.4.0 - 2026-08-27

### What's Changed

* Add ability to skip cache keys in the CacheRecorder by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/87
* Add regex matching to PatternMatcher by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/88

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/3.3.1...3.4.0

## 3.3.1 - 2026-07-22

### What's Changed

* Add support for Guzzle 8 by @GrahamCampbell in https://github.com/spatie/flare-client-php/pull/86

### New Contributors

* @GrahamCampbell made their first contribution in https://github.com/spatie/flare-client-php/pull/86

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/3.3.0...3.3.1

## 3.3.0 - 2026-07-15

### What's Changed

* Trim large span attributes at record time to bound trace memory by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/85

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/3.2.1...3.3.0

## 3.2.1 - 2026-07-03

### What's Changed

* Add expectAttributeIf test helper by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/83

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/3.2.0...3.2.1

## 3.2.0 - 2026-07-03

### What's Changed

* Support PHP 8.1 by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/82

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/3.1.2...3.2.0

## 3.1.2 - 2026-07-02

### What's Changed

* Strip embedded credentials from git remote URL by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/81

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/3.1.1...3.1.2

## 3.1.1 - 2026-07-01

### What's Changed

* Fix previous throwable stacktrace using the wrong exception's frames by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/79
* Fix CI failures caused by Symfony 8 by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/80

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/3.1.0...3.1.1

## 3.1.0 - 2026-05-28

### What's Changed

* Attach trace and span id to error reports by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/77
* Tie trace ids to active lifetime, add Flare::start()/end() by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/78

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/3.0.1...3.1.0

## 3.0.1 - 2026-05-24

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/3.0.0...3.0.1

## 3.0.0 - 2026-05-21

Logs, logs, logs and more:

- **Dedicated logging**: new first-class logger on `$flare->log()` that records messages in the OpenTelemetry log format, replacing `reportMessage()`. Log levels now use Monolog's `Level` enum. See [Logs](https://flareapp.io/docs/php/logs/introduction).
- **Dynamic sampling**: new `DynamicSampler` with `SamplingRule` definitions, letting you sample per entry point (e.g. health checks at 0%, checkout flows at 100%) without writing a custom sampler. See [Sampling](https://flareapp.io/docs/php/performance/sampling).
- **W3C traceparent aware sampling**: samplers now receive an `?bool $parentSampled` and can honor (or override) the inherited decision.
- **Job and queue recorders**: first-class `JobRecorder` and `QueueRecorder` as part of the standard recorder pipeline. See [Jobs and queues](https://flareapp.io/docs/php/data-collection/jobs-and-queues).
- **Subtask mode**: jobs and commands executed inside an existing trace attach as subtasks instead of starting a new root trace.
- **Entry points**: new `EntryPoint` value object resolved via `EntryPointResolver`, replacing the loose `entryPointClass` arguments and array context passed to samplers.
- **Attribute providers**: recorders now accept dedicated `RequestAttributesProvider`, `ResponseAttributesProvider`, `RouteAttributesProvider`, `CommandAttributesProvider`, `JobAttributesProvider`, and `UserAttributesProvider` contracts, replacing ad-hoc recorder arguments. See [Attribute providers](https://flareapp.io/docs/php/general/attribute-providers).
- **Lifecycle**: new `Lifecycle` class on `$flare->lifecycle` that owns flushing, resets, and internal state previously exposed on `Flare`. See [Application lifecycle](https://flareapp.io/docs/php/general/application-lifecycle).
- **Flare daemon bundled by default**: `DaemonSender` ships with the client and routes errors, traces, and logs through the local [Flare daemon](https://flareapp.io/docs/php/general/flare-daemon), with curl fallback when it's unreachable.
- **`Flare` accessors are now properties**: `$flare->tracer`, `$flare->backTracer`, `$flare->sentReports`, `$flare->ids`, `$flare->time`, `$flare->lifecycle`, `$flare->logger`, `$flare->reporter` are public readonly properties.
- **Removed**: error solutions (`collectSolutions()`, `withSolutionProvider()`, `AddSolutions` middleware, the `spatie/error-solutions` dependency).
- **Removed**: `reportMessage()`, `includeStackTraceWithMessages()`, `MessageLevels` enum, `sendReportsImmediately()`, `Flare::reset()`, `$flare->application()`.
- **Renamed**: `collectLogs()` → `collectLogsWithErrors()`, `ignoreLogs()` → `ignoreLogsWithErrors()`, `Tracer::addRawSpan()` → `addSpan()`.

Full upgrade notes in [UPGRADING.md](https://github.com/spatie/flare-client-php/blob/main/UPGRADING.md).

## 1.11.1 - 2026-05-15

- Fixed: client now accepts HTTP status 201 as a successful response

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.11.0...1.11.1

## 1.11.0 - 2026-03-17

### What's Changed

* Support Laravel 13 by @erikn69 in https://github.com/spatie/flare-client-php/pull/67

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.10.1...1.11.0

## 2.10.2 - 2026-02-23

- Handle non-JSON API responses gracefully in senders

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/2.10.1...2.10.2

## 2.10.1 - 2026-02-23

- fix deprecation warnings for PHP 8.5

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/2.10.0...2.10.1

## 2.10.0 - 2026-01-12

- Add new `FullStacktraceAndExceptionClassAndCode` grouping override

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/2.9.0...2.10.0

## 2.9.0 - 2026-01-08

- Allow censoring cookies and sessions

## 2.8.0 - 2025-12-19

- Allow configuring the Request and Console attribute providers

## 2.7.0 - 2025-12-11

### What's Changed

* Add exception `code` to report by @AlexVanderbist in https://github.com/spatie/flare-client-php/pull/60

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/2.6.0...2.7.0

## 2.6.0 - 2025-11-28

### What's Changed

* send testtrace function for flare:test command by @Marcel-Wil in https://github.com/spatie/flare-client-php/pull/59

### New Contributors

* @Marcel-Wil made their first contribution in https://github.com/spatie/flare-client-php/pull/59

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/2.5.3...2.6.0

## 2.5.3 - 2025-11-26

### What's Changed

* Fix for composer format by @YasserB94 in https://github.com/spatie/flare-client-php/pull/57
* Added Symfony 8 support to all symfony/* packages. by @thecaliskan in https://github.com/spatie/flare-client-php/pull/58

### New Contributors

* @YasserB94 made their first contribution in https://github.com/spatie/flare-client-php/pull/57
* @thecaliskan made their first contribution in https://github.com/spatie/flare-client-php/pull/58

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/2.5.2...2.5.3

## 2.5.2 - 2025-11-18

- Allow body field censoring using dot and wildcard syntax
- Fix an issue where the default of the git attribute provider was not provided

## 2.5.1 - 2025-11-13

- Fix an issue where the disabled Flare infrastructure wasn't working propertly

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/2.5.0...2.5.1

## 2.5.0 - 2025-11-07

- Add support for better trace context recording

## 2.4.0 - 2025-11-06

- Update the Git Collector to be more performant

## 2.3.3 - 2025-11-05

- Use local data to determine the telemetry sdk version

## 3.3.3 - 2025-11-05

- Use local data to determine the flare client telemetry version

## 2.3.2 - 2025-10-13

- Fix attribute key for span type in FilesystemRecorder.php

## 2.3.1 - 2025-10-10

- Better handling of success codes in the Api

## 2.3.0 - 2025-10-08

- Fixed an issue where disabled tracing could still trace
- Completely refactored the recorders, the old traits are now deprecated in favour of specific classes for spans or span events
- Fix an issue where attributes of spans & span events used in an error removed the span type

## 2.2.3 - 2025-10-06

- Fix sampler

## 2.2.2 - 2025-09-11

- Fix missing interface parameter

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/2.2.0...2.2.2

## 2.2.0 - 2025-09-11

### What's Changed

* Add additional methods to control custom context

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/2.1.0...2.2.0

## 2.1.0 - 2025-08-27

- Add support for stacktraces with logs

## 2.0.9 - 2025-08-25

- Laravel's `artisan` is now considered a vendor file in stacktraces
- Statamic's `please` is now considered a vendor file in stacktraces

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/2.0.8...2.0.9

## 2.0.8 - 2025-08-21

### What's Changed

* Fix issue where dns servers were contacted by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/44

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/2.0.7...2.0.8

## 2.0.7 - 2025-07-17

- Add support for Curl Options

## 2.0.6 - 2025-06-24

- Add support for disabled Flare

## 2.0.5 - 2025-06-03

- Add base path to stacktrace builder

## 2.0.3 - 2025-05-21

- Use base URL const

## 2.0.2 - 2025-05-21

- Use correct base url

## 2.0.1 - 2025-05-21

- Routing changes

## 2.0.0 - 2025-05-20

A complete rewrite of the package:

- You can read our new docs on what has changed
- There's an upgrade guide available

## 1.10.1 - 2025-02-14

### What's Changed

* Supports Laravel 12 by @duncanmcclean in https://github.com/spatie/flare-client-php/pull/38

### New Contributors

* @duncanmcclean made their first contribution in https://github.com/spatie/flare-client-php/pull/38

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.10.0...1.10.1

## 1.10.0 - 2024-12-02

- Add support for overriding grouping

## 1.9.0 - 2024-12-02

### What's Changed

* Fix PHP 8.4 deprecation errors by @Nationalcat in https://github.com/spatie/flare-client-php/pull/33
* Fix implicitly nullable parameters for PHP 8.4 by @maximal in https://github.com/spatie/flare-client-php/pull/34

### New Contributors

* @Nationalcat made their first contribution in https://github.com/spatie/flare-client-php/pull/33
* @maximal made their first contribution in https://github.com/spatie/flare-client-php/pull/34

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.8.0...1.9.0

## 1.8.0 - 2024-08-01

- Fix issues with symphony request payloads not behaving like they should.

## 1.7.0 - 2024-06-12

### What's Changed

* Refactor solutions by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/30

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.6.0...1.7.0

## 1.6.0 - 2024-05-22

### What's Changed

* Fix incorrect argument order when calling previous error handler by @RasmusStahl in https://github.com/spatie/flare-client-php/pull/28

### New Contributors

* @RasmusStahl made their first contribution in https://github.com/spatie/flare-client-php/pull/28

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.5.1...1.6.0

## 1.5.1 - 2024-05-03

### What's Changed

* Feature/configurable error levels by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/27

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.5.0...1.5.1

## 1.5.0 - 2024-05-02

### What's Changed

* Add support for handling errors by @rubenvanassche in https://github.com/spatie/flare-client-php/pull/26

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.4.4...1.5.0

## 1.4.4 - 2024-01-31

### What's Changed

* Drop Carbon dependency from composer.json by @jnoordsij in https://github.com/spatie/flare-client-php/pull/23

### New Contributors

* @jnoordsij made their first contribution in https://github.com/spatie/flare-client-php/pull/23

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.4.3...1.4.4

## 1.4.3 - 2023-10-17

### What's Changed

- [1.x] Tests against PHP 8.3 and adds Symfony 7 support by @nunomaduro in https://github.com/spatie/flare-client-php/pull/19

### New Contributors

- @nunomaduro made their first contribution in https://github.com/spatie/flare-client-php/pull/19

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.4.2...1.4.3

## 1.4.2 - 2023-07-28

- Loosen type for previous exception handler

## 1.4.1 - 2023-07-06

- Add better support for error exceptions

## 1.4.0 - 2023-06-28

- Add support for stack trace arguments

## 1.3.6 - 2023-04-12

- recognise AI generated solutions

## 1.3.5 - 2023-01-23

### What's Changed

- Update composer.json by @driesvints in https://github.com/spatie/flare-client-php/pull/3
- Prepare for Laravel 10 by @freekmurze in https://github.com/spatie/flare-client-php/pull/13

### New Contributors

- @driesvints made their first contribution in https://github.com/spatie/flare-client-php/pull/3

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.3.2...1.3.5

## 1.3.4 - 2023-01-23

- prep for Laravel 10

## 1.3.3 - 2023-01-23

- prepare for Laravel 10

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.3.2...1.3.3

## 1.3.2 - 2022-12-26

- reset glows

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.3.1...1.3.2

## 1.3.1 - 2022-11-16

### What's Changed

- Bug: Correct report sending logic by @Jellyfrog in https://github.com/spatie/flare-client-php/pull/11

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.3.0...1.3.1

## 1.3.0 - 2022-08-08

### What's Changed

- Allow `reportErrorLevels` to be 0 by @Jellyfrog in https://github.com/spatie/flare-client-php/pull/9
- Add support for filtering reports before sending to Flare by @Jellyfrog in https://github.com/spatie/flare-client-php/pull/10

### New Contributors

- @Jellyfrog made their first contribution in https://github.com/spatie/flare-client-php/pull/9

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.2.0...1.3.0

## 1.2.0 - 2022-05-16

- Add `php_version` as default `env` context

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.1.1...1.2.0

## 1.1.1 - 2022-05-11

## What's Changed

- Flat map request headers for PSR request context (47302e83d1b212ebc682bd18d7c27b8027db6c4e)
- Update .gitattributes by @angeljqv in https://github.com/spatie/flare-client-php/pull/6

## New Contributors

- @angeljqv made their first contribution in https://github.com/spatie/flare-client-php/pull/6

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.1.0...1.1.1

## 1.1.0 - 2022-03-11

- Allow passing an initialised `Report` instance to `report()` to Flare

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.0.5...1.1.0

## 1.0.5 - 2022-03-01

- Fix exception when `stage` is `null`

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.0.4...1.0.5

## 1.0.4 - 2022-02-28

## What's Changed

- catch throwable instead of exception for getSession failure by @ZeoKnight in https://github.com/spatie/flare-client-php/pull/5

## New Contributors

- @ZeoKnight made their first contribution in https://github.com/spatie/flare-client-php/pull/5

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.0.3...1.0.4

## 1.0.3 - 2022-02-25

## What's Changed

- Remove `arguments` from stacktrace frames (unused in UI and causing issues, see https://github.com/spatie/ignition/issues/48)
- Update .gitattributes by @PaolaRuby in https://github.com/spatie/flare-client-php/pull/4

## New Contributors

- @PaolaRuby made their first contribution in https://github.com/spatie/flare-client-php/pull/4

**Full Changelog**: https://github.com/spatie/flare-client-php/compare/1.0.2...1.0.3

## 1.0.2 - 2022-02-16

- avoid crash git info middleware

## 1.0.1 - 2022-02-04

- Add censor request headers middleware

## 1.0.0 - 2022-01-18

- initial release
