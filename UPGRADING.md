# Upgrading

There are some breaking changes you should be aware of. We've categorized them so you can prioritize. This guide covers the most common cases. Edge cases may not be covered, and PRs to improve it are welcome.

## From v2 to v3

The new version of the package adds better support for logging and tracing lifetimes. We don't expect upgrading to require a lot of code changes for typical applications.

If you use Flare through a framework package such as `spatie/laravel-flare`, the [framework integrator changes](#framework-integrator-changes) section does not apply to you and can be skipped.

### What's new in v3

A few new concepts are referenced throughout this guide:

- **Dedicated logging**: a first-class logger on `$flare->log()` that records messages in the OpenTelemetry log format. It replaces `reportMessage()` and is the recommended way to send standalone log entries to Flare. Log levels are now expressed with Monolog's `Level` enum.
- **Dynamic sampling**: a new `DynamicSampler` that selects a sample rate per entry point through `SamplingRule` definitions. Rules match on entry point name (with pattern support) and let you sample, for example, health checks at 0% and checkout flows at 100%, without writing a custom sampler.
- **Job and queue recorders**: first-class `JobRecorder` and `QueueRecorder` for tracing job execution and queue dispatches as part of the standard recorder pipeline.
- **Subtask mode**: jobs and commands executed inside an existing trace can now run as subtasks of that trace instead of starting a new root trace, keeping nested work attached to the originating request or command.
- **Lifecycle**: a new `Lifecycle` class, accessible via `$flare->lifecycle`, that manages report flushing, resets, and other internal state previously exposed on `Flare` directly.
- **Entry points**: a new `EntryPoint` value object, resolved by `EntryPointResolver`, that describes the request, command, or job that initiated a trace. It replaces the loose `entryPointClass` arguments and the array context previously passed to samplers.
- **Attribute providers**: contracts (`RequestAttributesProvider`, `ResponseAttributesProvider`, `RouteAttributesProvider`, `CommandAttributesProvider`, `JobAttributesProvider`, `UserAttributesProvider`) that recorders use to collect attributes. They replace the ad-hoc arguments and config-level provider hooks from v2.

### Enabling logging with the daemon

Standalone logging is opt-in. Enable it with `log()` on the config. Logs buffer in memory and are flushed when the request ends, so we recommend pairing it with the asynchronous [Flare daemon](https://github.com/spatie/flare-daemon) to keep delivery off the request thread.

Install and run the daemon locally, then point the client at it with `sendUsing()`:

```php
use Monolog\Level;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Senders\DaemonSender;

FlareConfig::make('your-api-key')
    ->log(minimalLevel: Level::Info)
    ->sendUsing(DaemonSender::class);
```

`DaemonSender` routes errors, traces, and logs through the daemon. If the daemon is unreachable, it falls back to direct delivery via curl.

### Changes

#### `reportMessage()` has been removed

Use the new logging functionality instead.

```php
// Before
$flare->reportMessage('Something happened', 'warning');

// After
$flare->log()->record('Something happened', \Monolog\Level::Warning);
```

The `includeStackTraceWithMessages()` config option has also been removed.

#### `collectLogs()` replaced by `collectLogsWithErrors()`

```php
// Before
$config->collectLogs(maxItemsWithErrors: 100, minimalLevel: MessageLevels::Debug);

// After
$config->collectLogsWithErrors(maxItems: 100, minimalLevel: \Monolog\Level::Info);
```

Use `ignoreLogsWithErrors()` instead of `ignoreLogs()`.

#### `MessageLevels` enum replaced by Monolog's `Level`

Everywhere you used `Spatie\FlareClient\Enums\MessageLevels`, use `Monolog\Level` instead. This includes glow recording and log configuration.

```php
// Before
$flare->glow()->record('Hello', MessageLevels::Debug);

// After
$flare->glow()->record('Hello', \Monolog\Level::Debug);
```

#### `report()`, `createReport()`, and `reportHandled()` now return `ReportFactory`

These methods previously returned a `Report`. They now return a `ReportFactory` (the `Report` class itself was removed). Update any callers that type-hinted the return value.

```php
// Before
$report = $flare->report($exception); // Report

// After
$reportFactory = $flare->report($exception); // ReportFactory
```

#### `filterReportsUsing` now receives a `ReportFactory`

The `Report` class has been removed. Update your closure type-hint to `ReportFactory`.

```php
// Before
$flare->filterReportsUsing(fn (Report $report) => ...);

// After
$flare->filterReportsUsing(fn (ReportFactory $report) => ...);
```

#### Solutions have been removed

Error solutions are no longer collected or sent.

```php
// Before
$config->collectSolutions();
$flare->withSolutionProvider(new MySolutionProvider());

// After
// Drop these calls. The spatie/error-solutions dependency can also be removed.
```

`$config->ignoreSolutions()`, the `AddSolutions` middleware, `defaultSolutionProviders()`, and the `solutionsProviderRepository` config option have all been removed as well.

### Framework integrator changes

These changes only affect code that integrates `flare-client-php` directly with a framework or runtime. If you use a maintained framework package such as `spatie/laravel-flare`, you can skip this section.

#### `$flare->application()` has been removed

The `ApplicationRecorder` has been removed. Reset and flush behavior now lives on `Lifecycle`.

```php
// Before
$flare->application()->reset();

// After
$flare->lifecycle->reset();
```

#### Public methods on `Flare` are now properties

`$flare->tracer()`, `$flare->backTracer()`, and `$flare->sentReports()` are now public readonly properties.

```php
// Before
$flare->tracer()->startSpan(...);
$flare->backTracer()->trace(...);
$reports = $flare->sentReports();

// After
$flare->tracer->startSpan(...);
$flare->backTracer->trace(...);
$reports = $flare->sentReports;
```

The same applies to `$ids`, `$time`, `$lifecycle`, `$logger`, and `$reporter`, which are now public readonly properties on `Flare` as well.

#### `sendReportsImmediately()` and `Flare::reset()` have been removed

Both are now managed internally by `Lifecycle`. Drop any direct calls.

#### Custom `Sampler` signature changed

`Sampler::shouldSample()` now receives an `EntryPoint` instead of an array.

```php
// Before
public function shouldSample(array $context): bool;

// After
public function shouldSample(EntryPoint $entryPoint): bool;
```

The `SamplingType` enum was also removed. Use the boolean `$tracer->sampling` (or `isSampling()`) instead.

#### Changes in `Tracer`

- `startTrace()` signature changed.
- `addRawSpan()` was renamed to `addSpan()`.
- `startTraceWithSpan()`, `setCurrentSpanId()`, and `trashCurrentTrace()` were removed without replacement. They're managed internally now.

#### Custom `SpansRecorder` implementations

Starting a trace from within a recorder is no longer possible. The `$canStartTrace` and `$parentId` parameters were removed from `startSpan()`. The deprecated `RecordsSpans`, `RecordsSpanEvents`, and `RecordsEntries` traits were also removed.

#### `Sender` interface changes

Custom `Sender` implementations need updating. The interface now uses `FlareEntityType` instead of `FlarePayloadType` and has an additional `bool $test` parameter.

#### Attribute providers replace ad-hoc recorder arguments

In v2, recorders accepted loose arguments (request objects, status codes, route strings, entry point classes) directly on `recordStart()` and `recordEnd()`. In v3, recorders accept attribute providers that implement dedicated contracts. The provider is responsible for turning a framework-specific object (a Symfony request, an Artisan input, a route definition) into the attributes Flare records.

The previous `FlareConfig::userAttributesProvider()`, `requestAttributesProvider()`, and `consoleAttributesProvider()` config options have been removed. Pass providers directly to the relevant recorder method, typically from your framework integration. Each recorder also ships `recordStartFrom*` / `recordEndFrom*` helpers that build the standard providers for you.

Entry point information is no longer passed to recorders. It is resolved through the new `EntryPointResolver`.

##### `RequestRecorder::recordStart()`

```php
// Before
$flare->request()->recordStart(
    request: $request,
    route: '/users/{id}',
    entryPointClass: UserController::class,
);

// After
$flare->request()->recordStart(
    new SymfonyRequestAttributesProvider($redactor, $request),
);

// Or use the helper that builds the provider for you:
$flare->request()->recordStartFromSymfonyRequest($request);
```

##### `RequestRecorder::recordEnd()`

```php
// Before
$flare->request()->recordEnd(
    responseStatusCode: 200,
    responseBodySize: 1024,
);

// After
$flare->request()->recordEnd(
    responseAttributesProvider: new SymfonyResponseAttributesProvider($redactor, $response),
);

// Or use the helpers:
$flare->request()->recordEndFromSymfonyResponse($response);
$flare->request()->recordEndFromDefined(statusCode: 200, bodySize: 1024);
```

`recordEnd()` accepts optional `RequestAttributesProvider`, `ResponseAttributesProvider`, `RouteAttributesProvider`, and `UserAttributesProvider` arguments.

##### `CommandRecorder::recordStart()`

```php
// Before
$flare->command()->recordStart(
    command: 'app:sync',
    arguments: $input,
    entryPointClass: SyncCommand::class,
);

// After
$flare->command()->recordStart(
    new SymfonyInputCommandAttributesProvider($input, 'app:sync', SyncCommand::class),
);

// Or use the helpers:
$flare->command()->recordStartFromSymfonyInput('app:sync', $input, SyncCommand::class);
$flare->command()->recordStartFromCli('app:sync', SyncCommand::class);
$flare->command()->recordStartFromDefined('app:sync', $arguments, SyncCommand::class);
```

##### `RoutingRecorder::recordRoutingEnd()`

```php
// Before
$flare->routing()->recordRoutingEnd(['http.route' => '/users/{id}']);

// After
$flare->routing()->recordRoutingEnd(
    new PhpRouteAttributesProvider('/users/{id}', method: 'GET'),
);

// Or use the helper:
$flare->routing()->recordRoutingEndFromDefined('/users/{id}', method: 'GET');
```

#### `traceLimits()` no longer accepts a `TraceLimits` object

Pass the limit values directly as named arguments to `traceLimits()` instead of constructing a `TraceLimits` object.


## From v1 to v2

Version two of the package has been a complete rewrite, we've added some interesting points in this upgrade guide but advise you to read the docs again.

- The package requires now PHP 8.2 or higher.
- The `anonymizeIp()` method was renamed to `censorClientIps()` and should now be called on Flare config object
- The `censorRequestBodyFields()` method was renamed to `censorBodyFields()` and should now be called on Flare config object
- The `reportErrorLevels` method should now be called on Flare config object
- The `overrideGrouping` method should now be called on Flare config object
- The `$flare->context()` method works a bit different now, the concept of groups has been removed. A single context item still can be added like this:

```php
$flare->context('key', 'value'); // Single item
```

Multiple context items can be added like this:

```php
$flare->context([
    'key' => 'value',
    'key2' => 'value2',
]);
```
- The `group` method to add context data has been removed, you should just use the `context()` method
- We've changed how glows are added (MessageLevels is now an enum and slightly renamed):

```php
$flare->glow('This is a message from glow!', MessageLevels::DEBUG); // Old way

$flare->glow()->record('This is a message from glow!', MessageLevels::Debug); // New way
```

- The `argumentReducers()` method has been removed, you should use the `collectStackFrameArguments` method on the Flare config object
- It is still possible to add custom middleware. We did remove the possibility to add middleware inline with a closure and adding a middleware class must now be done like this on the Flare config object:

```php
$config->collectFlareMiddleware([
    MyMiddleware::class => [],
]);
```

- All the recorders and middleware provided by the package are rewritten or removed, if you extended any of these please check them.
