---
title: Sending logs to Flare 
---


In addition to handling your application errors, you may also want to send specific error messages to Flare.

These error messages are not necessarily errors but log statements that exceed a specified threshold—think of critical logs that your application sends and that you want to be notified about.

## Activating/Deactivating log reporting

Sending a log can be done as such:

```php
$flare->reportMessage('This is a Flare log message', 'info');
```

There are several log levels you can use, which are the same as the ones defined in [PSR-3](https://www.php-fig.org/psr/psr-3/):

## Sending stack traces with your logs

By default, we do not send stack traces alongside your log messages, this because it requires us to do a backtrace on every log call, which can be quite expensive.

If you want to enable sending stack traces, you can do so by changing your config like this:

```php
$config->includeStackTraceWithMessages()
```
