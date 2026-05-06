---
title: Spans 
---


Flare automatically collects a lot of information about everything happening within your application. But sometimes you want to add your own custom events to Flare.

Internally, Flare is following the [OpenTelemetry](https://opentelemetry.io/) specification. This means you can add two types of events to Flare:

- Spans
- SpanEvents

A span can be seen as an event that takes some time to complete, such as handling a request or running a query. A span always has a start and end time and can be nested.

A span event is a sub-event of a span. It always requires a parent's span to be attached to it. A span event typically has no start or end time but happens at an exact point in time. Some examples of span events are logs being written, errors happening, etc.

Each span in the end belongs to a trace, which is a collection of related spans. A trace can be distributed; for example, a request handled by your server triggers a job that runs on another server, both generating spans in the same trace.

## Starting a trace

To start a trace, you can use the `startTrace` method on the tracer:

```php
$flare->tracer->startTrace();
```

The tracer will now check whether we should sample this time and thus keep track of everything happening within the application.

If you want to force starting a trace manually (without checking the sampler), then you can do the following:

```php
$flare->tracer->startTrace(forceSampling: true);
```

We recommend you use the application lifecycle events as the root span of an application. As an added bonus, this will also automatically start a trace for you:

```php
$flare->application->recordStart();

// Run your application

$flare->application->recordEnd();
```

You can read more about application lifecycle events [here](/docs/php/data-collection/application-lifecycle).

## Creating spans

A span always requires the following properties:

- A trace ID
- A span ID
- A name
- A start and end time

The following properties are optional:

- A parent span ID
- Attributes adding context to the span

The easiest way to get started with adding spans using the tracer:

```php
$flare->tracer->span('My custom span', function (){
    // Do an operation
});
```

It is possible to nest spans:

```php
$flare->tracer->span('My custom span A', function () use ($flare){
    // Do an operation
    
    $flare->tracer->span('My custom span B', function (){
        // Do another operation
    });
});
```

You can add additional attributes to a span to provide additional context:

```php
$flare->tracer->span('My custom span', function (){
    // Do an operation
}, attributes: [
    'key' => 'value'
]);
```

If you want to add attributes after the operation has run, based on the result of the operation, you can add an `endAttributes` Closure:

```php
$flare->tracer->span('My custom span', function (){
    // Do an operation
    
    return $result;
}, endAttributes: function ($result) {
    return ['result' => $result]
});
```

When you don't want to use a closure to run operations, you can also start and end a span manually:

```php
$span = $flare->tracer->startSpan('My custom span');

// Do an operation

$flare->tracer->endSpan($span);
```

It is possible to pass additional attributes to the span as such:

```php
$span = $flare->tracer->startSpan('My custom span', attributes: [
    'key' => 'value'
]);

// Do an operation

$flare->tracer->endSpan($span, additionalAttributes: function ($result) {
    return ['result' => $result]
});
```

Calling `endSpan` without a span will end the current span in the trace:

```php
$flare->tracer->startSpan('My custom span');

// Do an operation

$flare->tracer->endSpan();
```

It is possible to define the times of the span manually:

```php
use Spatie\FlareClient\Time\TimeHelper;

$span = $flare->tracer->startSpan('My custom span', time: TimeHelper::now());

// Do an operation

$flare->tracer->endSpan($span, time: TimeHelper::now());
```

Flare works with nano timings internally, so you can also use the `TimeHelper` to convert your timings to nanoseconds:

```php
TimeHelper::now(); // Current time
TimeHelper::phpMicroTime(microtime(true)); // Parses PHP's microtime
Timehelper::dateTimeToNano(new DateTime()); // Parses a DateTime object

TimeHelper::microseconds(1000); // 1000 microseconds
TimeHelper::milliseconds(200); // 200 milliseconds
TimeHelper::seconds(30); // 30 seconds
TimeHelper::minutes(4); // 4 minutes
```

Nested spans will automatically set their parent span ID, so you don't need to worry about that.

## Creating span events

A span always requires the following properties:

- A name
- A timestamp

The following properties are optional:

- Attributes adding context to the span event

Please remember that span events are consistently attached to a parent span, so you must start a span first.

The easiest way to get started with adding span events using the tracer:

```php
$flare->tracer->spanEvent('My custom span event');
```

It is possible to add additional attributes to a span event to provide additional context:

```php
$flare->tracer->spanEvent('My custom span event', attributes: [
    'key' => 'value'
]);
```

You can set the timestamp of the span event manually:

```php
use Spatie\FlareClient\Time\TimeHelper;

$flare->tracer->spanEvent('My custom span event', time: TimeHelper::now());
```

## Ending a trace

In the end, when you're ready to send the trace to Flare, you can call the `endTrace` method on the tracer:

```php
$flare->tracer->endTrace();
```

When using the application lifecycle events, this will be done automatically.

## Propagation

Traces can sometimes span multiple entry points, such as commands, jobs or web requests. For example, you could have a web request that triggers a job, which triggers another job. In this case, you want to propagate the trace ID and span ID to the next entry point so that you'll have the complete picture of the trace in Flare.

To propagate, we'll need to pass on the trace ID and span ID to the next entry point; the traceparent is a single string containing both.

```php
$traceparent = $flare->tracer->traceparent();

dispatch(new DoSomethingJob($traceparent));
```

Then, in the next entry point, you can use the `startTrace` method to start a trace with the traceparent:

```php
$flare->tracer->startTrace(traceparent: $traceparent);
```

When a trace is already sampling events, the application will keep sampling them. If the trace is not sampled, the traceparent will still exist, but further application events won't be sampled.

## Using and creating recorders

Until now, we've seen how to trace spans and span events in a performance context. This context is also valuable when an error is thrown in your application.

That's why Flare provides another way to save spans and span events, using recorders.

A recorder is a class that boots before Flare boots and listens for events within your application. It can record spans and span events, which will be used for performance monitoring. Additionally, when an error occurs, all spans and span events within the recorder will be sent alongside the error to Flare.

Flare provides many default recorders, such as the QueryRecorder, ViewRecorder, RequestRecorder, and so on. For example, when recording a query in Flare, you'll call `$flare->query()->record(...)`; internally, Flare will call the `QueryRecorder` to record the query.

It is possible to add your own recorders, let's dive into it.

## Creating a Span recorder

A span recorder can be created by implementing the `SpansRecorder` and adding the `RecordsSpans` trait:

```php
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Concerns\Recorders\RecordsSpans;
use Spatie\FlareClient\Enums\RecorderType;

class MyQueryRecorder implements SpansRecorder
{
    use RecordsSpans;
    
    public static function type(): string|RecorderType
    {
        return 'my_query';
    }
    
    public function recordQueryStart(string $sql): void
    {
        $this->startSpan("Query - {$sql}");
    }
    
    public function recordQueryEnd(): void
    {
         $this->endSpan();
    }
}
```

Now we'll need to register the recorder with Flare:

```php
$flare->collectRecorders([
    MyQueryRecorder::class => [],
]));
```

You'll always need to provide the recorder's class as the key, and you can provide additional configuration as the value.

Now we can call our recorder:

```php
$flare->recorder('my_query')->recordQueryStart("SELECT * FROM users");

// Run your application

$flare->recorder('my_query')->recordQueryEnd();
```

When a trace is started, the span is added to it. If an error occurs, the span is sent to Flare alongside the error.

When there's no trace sampling, the span will still be recorded for sending alongside a potential future error to Flare, but it won't be used for performance monitoring.

In many cases, you'll probably want to automatically start a trace when a span is recorded. For example, a recorder handling requests should be able to start a trace (based upon your sampling preferences). This functionality can be enabled on a recorder basis as such:

```php
class MyRequestRecorder implements SpansRecorder
{
    use RecordsSpans;
    
    protected function canStartTraces(): bool
    {
        return true;
    }
    
    // Other methods ...
}
```

Now, when starting a span in the recorder, it will automatically check the tracer (based on the sample rate) to determine whether it should start a trace.

You can configure a recorder even further by passing a configuration array to the `collectRecorders` method:

```php
use Spatie\FlareClient\Time\TimeHelper;

$flare->collectRecorders([
    MyRecorder::class => [
        'with_traces' => true,
        'with_errors' => true,
        'max_items_with_errors' => 10,
        'find_origin' => true,
        'find_origin_threshold' => TimeHelper::milliseconds(300),
    ],
]);
```

The following options can be configured:

- `with_traces`: Whether the recorder should add spans to the trace
- `with_errors`: Whether the recorder should add spans to the error
- `max_items_with_errors`: The maximum number of items to be recorded when an error happens
- `find_origin`: Whether the recorder should find the origin of the span (where it was started in the code)
- `find_origin_threshold`: The threshold in milliseconds to find the origin of the span in nanoseconds, when `null`, the origin will be added for all spans

Note: Be careful with the `find_origin` option. It will add a lot of overhead to the span creation. It is recommended that you only use this option when you really need it.

You're able to add custom configuration options by overwriting the `configure` method in the recorder:

```php
class MyQueryRecorder implements SpansRecorder
{
    use RecordsSpans;
    
    protected bool $advancedMode = false;

    public function configure(array $config): void
    {
        $this->advancedMode = $config['advanced_mode'] ?? false;
    
        $this->configureRecorder($config); // Ensures the recorder is configured correctly
    }
}
```

You can now define the extra config option as such:

```php
$flare->collectRecorders([
    MyQueryRecorder::class => [
        'advanced_mode' => true,
    ],
]);
```

If you'll need external dependencies, you can use the constructor to inject them. Since the framework-agnostic Flare instance has its own container without auto wiring, you'll need to set up how the object gets created:

```php
public function __construct(
    protected Tracer $tracer,
    prootected MyDependency $myDependency,
    protected array $config,
) {
    $this->configure($config); // required to configure the recorder
}

public static function register(ContainerInterface $container, array $config): Closure
{
    return fn () => new self(
        $container->get(Tracer::class),
        $container->get(MyDependency::class),
        $config,
    );
}
```

Be sure to always add the `Tracer` dependency to the constructor. This is required to start and end spans and call the `configure` method with the config provided.

## Creating a span events recorder

A span events recorder can be created by implementing the `SpanEventsRecorder` and adding the `RecordsSpanEvents` trait:

```php
use Spatie\FlareClient\Contracts\Recorders\SpanEventsRecorder;
use Spatie\FlareClient\Concerns\Recorders\RecordsSpanEvents;
use Spatie\FlareClient\Enums\RecorderType;

class MyLogRecorder implements SpanEventsRecorder
{
    use RecordsSpanEvents;
    
    public static function type(): string|RecorderType
    {
        return 'my_log';
    }
    
    public function record(string $message): void
    {
        $this->spanEvent($message);
    }
}
```

Now we'll need to register the recorder with Flare:

```php
$flare->collectRecorders([
    MyLogRecorder::class => [],
]));
```

You'll always need to provide the recorder's class as the key, and you can provide additional configuration as the value.

Now we can call our recorder:

```php
$flare->recorder('my_log')->record("My custom log message");
```

While it is not possible to start traces with a spans event recorder, it is possible to configure and add external dependencies to the recorder in the same way as with the spans recorder.


## Creating a Span recorder without the `RecordsSpans` trait

A span recorder can be created by implementing the `SpansRecorder`:

```php
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;

class MyRecorder implements SpansRecorder
{
    public static function type(): string|RecorderType
    {
        // This is the type of recorder; it can be used to identify the recorder.
        // You can select one of the default types or create your own.
    }

    public function boot(): void
    {
        // When booting up Flare, this method will be called.
        // It is an excellent place to start the recorder and listen for events.
    }

    public function reset(): void
    {
        // Every time an error is sent, a new request is handled or a job started, all recorders will be reset
        // In this method, you can clear all the spans that have been recorded
    }

    /** @return array<Span> */
    public function getSpans(): array
    {
        // When an error happens, all spans that need to be sent to Flare should be returned here.
    }
}
```

The recorder defines four methods to implement, while the `getSpans` method provides the recorded spans when an error happens. When you also want to record spans for performance monitoring, you'll also need to add the span to the tracer as such:

```php
public function boot(): void
{
    $this->tracer->span('My custom span', function () {
        // Do an operation
    });
}
```

Or you can use the `startSpan` and `endSpan` methods to manually start and end a span:

```php
$span = $this->tracer->startSpan('My custom span');

// Do an operation

$this->tracer->endSpan($span);
```

If you've created a span manually, it can be added as such:

```php
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Time\TimeHelper;

$span = new Span(
    traceId: $tracer->currentTraceId(),
    spanId: $tracer->ids()->span(),
    parentSpanId: $tracer->currentSpanId(),
    name: 'My custom span',
    start: TimeHelper::dateTimeToNano($time),
    duration: TimeHelper::milliseconds(300),
);

$this->tracer->addRawSpan($span);
```

## Creating a SpanEvents recorder without the `RecordsSpanEvents` trait

A span events recorder can be created by implementing the `SpanEventsRecorder`:

```php
use Spatie\FlareClient\Contracts\Recorders\SpanEventsRecorder;
use Spatie\FlareClient\Enums\RecorderType;

class MySpanEventsRecorder implements SpanEventsRecorder
{
    public static function type(): string|RecorderType
    {
        // This is the type of recorder; it can be used to identify the recorder.
        // You can select one of the default types or create your own.
    }

    public function boot(): void
    {
        // When booting up Flare, this method will be called.
        // It is an excellent place to start the recorder and listen for events.
    }

    public function reset(): void
    {
        // Every time an error is sent, a new request is handled or a job started, all recorders will be reset
        // In this method, you can clear all the span events that have been recorded
    }

    public function getSpanEvents(): array
    {
        // When an error happens, all span events that need to be sent to Flare should be returned here.
    }
}
```

The recorder defines four methods to implement, while the `getSpanEvents` method provides the recorded span events when an error happens. When you also want to record span events for performance monitoring, you'll also need to add the span events to the current span as such:

```php

use Spatie\FlareClient\Spans\SpanEvent;

public function boot(): void
{
    $span = $this->tracer->currentSpan();
    
    if ($span === null) {
        return;
    }
    
    $span->addEvent(new SpanEvent(
        name: 'My custom span event',
        time: TimeHelper::now(),
        attributes: [
            'key' => 'value',
        ],
    ));
}
``` 
