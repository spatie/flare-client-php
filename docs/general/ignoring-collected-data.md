---
title: Ignoring collected data 
---

## Ignoring exceptions

The Flare client will always send all errors to Flare. You can change this behaviour by filtering the errors with a callable:

```php
$config->filterExceptionsUsing(
    fn(Throwable $throwable) => !$throwable instanceof AuthorizationException
);
```

## Ignoring exception reports

Additionally, you can provide a callable to the `FlareConfig::filterReportsUsing` method to stop a report from being sent to Flare. Compared to `filterExceptionsCallable`, this can also prevent logs and errors from being sent.

```php
$config->filterReportsUsing(function(Report $report)  {
    // return a boolean to control whether the report should be sent to Flare
    return true;
});
```

## Ignoring errors

Finally, it is also possible to set the levels of errors reported to Flare as such:

```php
$config->reportErrorLevels(E_ALL & ~E_NOTICE); // Will send all errors except E_NOTICE errors
```

## Ignoring spans

At the moment, it is not possible to filter out spans since they depend on each other. Removing spans would break the inheritance required for performance monitoring.

## Ignoring Flare data collection

Flare collects a lot of data by default. We define many types of collects (queries, requests, etc.) that are sent to Flare. You can ignore these collects with the `ignore*` methods on the Flare config instance. 
