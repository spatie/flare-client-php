---
title: Logs 
---


Flare can collect information about the logs being written in your application. This includes:

- The log level
- The log message
- The log context

This functionality is enabled by default, but you can disable it by calling `ignoreLogs()` on the Flare config:

```php
$config->ignoreLogs();
```

You can configure the maximum number of logs tracked while collecting data in the case of an error as follows:

```php
$config->collectLogs(maxItemsWithErrors: 10);
```

## Collecting logs

We cannot automatically collect logs in the framework-agnostic version of the package. You can manually add logs as such:

```php
use Spatie\FlareClient\Enums\MessageLevels;

$flare->log()->record(
    message: 'This is a log message',
    level: MessageLevels::Debug,
    context: [
        'team_id' => 1
    ]
);
```

The `MessageLevels` enum follows the [RFC5424](https://datatracker.ietf.org/doc/html/rfc5424) standard. The following levels are available:

| Level | Description |
| ----- | ----------- |
| `MessageLevels::Emergency` | Emergency: system is unusable |
| `MessageLevels::Alert` | Alert: action must be taken immediately |
| `MessageLevels::Critical` | Critical: critical conditions |
| `MessageLevels::Error` | Error: error conditions |
| `MessageLevels::Warning` | Warning: warning conditions |
| `MessageLevels::Notice` | Notice: normal but significant condition |
| `MessageLevels::Informational` | Informational: informational messages |
| `MessageLevels::Debug` | Debug: debug-level messages | 