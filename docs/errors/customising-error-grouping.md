---
title: Customising error grouping
---


Flare has a [unique grouping](/docs/flare/errors/error-grouping) algorithm that groups similar error occurrences into errors to make understanding what's going on in your application easier.

While the default grouping algorithm works for 99% of cases, there are some cases where you might want to customise it.

This can be done on an exception class basis using the `overrideGrouping` method:

```php
use Spatie\FlareClient\Enums\OverriddenGrouping;

$flare->overrideGrouping(SomeExceptionClass::class, OverriddenGrouping::ExceptionClass);
```

## Available grouping strategies

| Strategy | Groups by | Use case |
|----------|-----------|----------|
| `ExceptionClass` | Exception class only | Group all exceptions of the same type together, regardless of message or stack trace |
| `ExceptionMessage` | Exception message only | Group by message content, useful when the exception class is generic |
| `ExceptionMessageAndClass` | Exception class + message | More granular grouping, but be careful as slightly different messages create separate groups |
| `FullStacktraceAndExceptionClassAndCode` | Full stacktrace + exception class + exception code | Useful for API exceptions like `ClientException` where the exception is always thrown from the same location, but you want to distinguish by HTTP status code |

**Warning:** Be careful when choosing a grouping strategy. If the values you group by are unique for every occurrence (e.g. exception messages containing timestamps or IDs), each occurrence will create a separate error in Flare.
