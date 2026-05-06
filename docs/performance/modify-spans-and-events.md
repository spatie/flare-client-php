---
title: Modify spans & span events 
---


It is possible to modify spans as such:

```php
$flare->configureSpans(fn(Span $span) => $span->addAttribute('key', 'value'));
```

The following closure will be called each time a span (which ended) is added to the trace or when a span already present in the trace ends. The closure should return `void` or the span itself.

## Span events

It is also possible to modify span events using a closure:

```php
$flare->configureSpanEvents(fn(SpanEvent $spanEvent) => $spanEvent->addAttribute('key', 'value'));
```

The following closure will be called after the `configureSpans` closure.

It is possible to return `null` as a value from the closure, which will remove the span event from the span. Deleting spans is technically not possible since spans have a child-parent relation, which could break when deleting one of them. 