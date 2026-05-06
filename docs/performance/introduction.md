---
title: Introduction 
---


Flare allows you to run performance monitoring on your application. This means you can see how long certain parts of your code take to execute.

To do this, we'll follow the Open Telemetry tracing standard. Let's give you a quick introduction:

> An application always starts a **trace** for every request, command or job. A sampler decides whether a trace will be **sampled** based on the sampling rate and a randomiser. When a trace is sampled, spans will be recorded for the trace. A **span** is a work unit executed within a trace. A span can have a parent span, which means that the span is executed within the timeframe of another span. A span always has a start and end time. Whenever a time-based event happens within a span (with only a single timestamp and no specific start or end time), then we'll call such an event a **span event**. Sometimes a trace is distributed over multiple machines/services, think a request triggering a queued job. In such a case, the application sends the trace_id, span_id and sampling decision to the next server/service to continue the trace, we call this **propagation**.

That's it, you're now a tracing expert!

## Enabling performance monitoring

To enable performance monitoring, configure Flare as follows:

```php
$config->trace(true);
```

## Starting traces

You can start a trace by creating an initial application span as follows:

```php
$flare->application()->recordStart();
```

Now it's time to run your application and additional spans, more on that later.

When your application exists, you should end the trace as such:

```php
$flare->application()->recordEnd();
```

This will close the trace and send it to Flare.

It is possible to record more application lifecycle events, like the registration, boot and termination of your application. You can read more about that [here](/docs/php/data-collection/application-lifecycle).

## Collecting spans & span events

We've got a complete chapter on collecting data for Flare, from queries to logs to external HTTP calls. While it is technically possible to create your own spans and span events, we recommend using the built-in data collectors.

For example, tracing a query is as simple as:

```php
use Spatie\FlareClient\Time\TimeHelper;

$config->collectQueries();

$flare->database()->recordQuery(
    sql:'SELECT * FROM users WHERE id = ?',
    duration: TimeHelper::milliseconds(300),
    bindings: [1],
    databaseName: 'Flare',
    driverName: 'mysql'
);
```

When recording this span, the end time will be the current time, and the start time will be the current time minus the duration.

Notice the `TimeHelper`? Open telemetry collects the duration in nanoseconds, but PHP can only keep track of milliseconds. The `TimeHelper` will convert the milliseconds to nanoseconds for you.

We have excellent documentation on traces, spans, span events, and propagation. You can read it [here](/docs/php/data-collection/spans).

## Disabling performance monitoring

Sometimes you might not want to enable performance monitoring for your application. This can be done as such:

```php
$config->trace(false);
``` 
