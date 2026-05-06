---
title: Redis commands 
---


Flare can collect information about the Redis commands being executed in your application. This includes:

- The command
- The command parameters
- The namespace (database)
- The Redis server IP & port

This functionality is **disabled** by default, but you can enable it by calling on the Flare config:

```php
$config->collectRedisCommands();
```

You can configure the maximum number of Redis commands tracked while collecting data in the case of an error as such:

```php
$config->collectRedisCommands(maxItemsWithErrors: 10);
```

## Collecting Redis commands

We cannot automatically collect Redis commands in the framework-agnostic version of the package. You can manually add Redis commands as such:

```php
use Spatie\FlareClient\Time\TimeHelper;

$flare->redis()->record(
    command: 'SET',
    parameters: ['key', 'value'],
    duration: TimeHelper::microseconds(300),
    namespace: 'db0',
    serverIp: '192.168.0.1',
    serverPort: 6379,
);
```

The duration should be in milliseconds. When you have a start and end event within your code, for the event, you can use the `recordStart` and `recordEnd` methods:

```php
$flare->redis()->recordStart(
    command: 'SET',
    parameters: ['key', 'value'],
    namespace: 'my-namespace',
    namespace: 'db0',
    serverIp: '192.168.0.1',
    serverPort: 6379,
);

// Do your redis command

$flare->redis()->recordEnd();
```

It is always possible to add extra attributes to the Redis command:

```php
$flare->redis()->record(
    command: 'SET',
    parameters: ['key', 'value'],
    duration: TimeHelper::microseconds(300),
    namespace: 'db0',
    serverIp: '192.168.0.1',
    serverPort: 6379,
    attributes: [
        'redis.connection' => 'my-connection',
    ]
);
``` 