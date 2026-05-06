---
title: Application lifecycle 
---


Flare can collect the lifecycle events of your application.

There are a few different types of events that can be collected:

- The whole application runs from start to finish
- The time it took to register everything in a dependency container
- The time it took to boot up the application/framework
- In the end, the time it took to terminate the application

Application lifecycle events are enabled by default and cannot be disabled.

## Collecting application lifecycle events

Every application starts with the initialisation of the application:

```php
$flare->applicationLifecycle()->recordStart();
```

For each lifecycle event, you can also define an additional time when the event happened:

```php
use Carbon\CarbonImmutable;
use Spatie\FlareClient\Time\TimeHelper;

$flare->applicationLifecycle()->recordStart(
    time: TimeHelper::dateTimeToNano(CarbonImmutable::now()),
);
```

And add extra attributes to the event:

```php
$flare->applicationLifecycle()->recordStart(attributes: [
    'framework.version' => '12'
]);
```

When your application registers services in the container, you can keep track of it as follows:

```php
$flare->applicationLifecycle()->recordRegistering();

// Register services within the container

$flare->applicationLifecycle()->recordRegistered();
```

Next up, when your application is booting, you can keep track of it as such:

```php
$flare->applicationLifecycle()->recordBooting();

// Boot your application

$flare->applicationLifecycle()->recordBooted();
```

Now it's time to run your application, feel free to keep track of requests, jobs, commands, queries, ...:

```php
use Spatie\FlareClient\Time\TimeHelper;

$flare->request()->recordStart();

$flare->query()->record("select * from users", TimeHelper::milliseconds(300), ['id' => 1]);

$flare->request()->recordEnd();
```

In the end, when your application runs and starts terminating, you can keep track of it as follows:

```php
$flare->applicationLifecycle()->recordTerminating();

// Terminate your application

$flare->applicationLifecycle()->recordTerminated();
```

When your application is entirely ended, you should call:

```php
$flare->applicationLifecycle()->recordEnd();
``` 