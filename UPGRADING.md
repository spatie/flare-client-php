# Upgrading

Because there are many breaking changes an upgrade is not that easy. There are many edge cases this guide does not cover. We accept PRs to improve this guide.

## From v2 to v3

The new version of the package adds better support for logging and tracing lifetimes.

We don't expect that using the new version will require a lot of code changes, but there are some breaking changes you should be aware of. We have categorized these changes into high, medium, and low impact changes to help you prioritize your upgrade efforts.

### High impact changes

#### `reportMessage()` has been removed

Use the new logging functionality instead:

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

#### `$flare->application()` has been removed

The `ApplicationRecorder` has been removed in favor of the new `Lifecycle` class accessible via `$flare->lifecycle`.

### Medium impact changes

#### `MessageLevels` enum replaced by Monolog's `Level`

Everywhere you used `Spatie\FlareClient\Enums\MessageLevels`, use `Monolog\Level` instead. This includes glow recording and log configuration.

#### `filterReportsUsing` now receives a `ReportFactory` instead of `Report`

The `Report` class has been removed. Update your closure type-hint to `ReportFactory`.

#### Changes in `Tracer`

- `startTrace()` signature changed
- `addRawSpan()` was renamed to `addSpan()`

#### Custom `SpansRecorder` implementations

Starting a trace from within a recorder is no longer possible. The `$canStartTrace` and `$parentId` parameters were removed from `startSpan()`. The deprecated `RecordsSpans`, `RecordsSpanEvents`, and `RecordsEntries` traits were also removed.

### Low impact changes

- `$flare->sentReports()` is now a property: `$flare->sentReports`
- `sendReportsImmediately()` and `Flare::reset()` have been removed, managed internally by `Lifecycle`
- `traceLimits()` no longer accepts a `TraceLimits` object
- The `Sender` interface now uses `FlareEntityType` instead of `FlarePayloadType` and has an additional `bool $test` parameter

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
