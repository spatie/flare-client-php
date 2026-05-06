---
title: Cache events 
---


An application can use a cache to store data that is expensive to compute. Flare can collect information about the cache events in your application.

Flare will collect the following information:

- The cache key
- The cache store
- The cache operation (`CacheOperation::Get`, `CacheOperation::Set`, `CacheOperation::Forget`)
- The cache result (`CacheResult::Hit`, `CacheResult::Miss`, `CacheResult::Sucess`, `CacheResult::Failure`)

It is possible to disable this behaviour by ignoring cache events in the Flare config:

```php
$config->ignoreCacheEvents();
```

The amount of cache events tracked while collecting data in the case of an error can be configured as such:

```php
$config->collectCacheEvents(maxItemsWithErrors: 50);
```

It is also possible to limit the types of cache operations that are collected:

```php
$config->collectCacheEvents(
    operations: [
        CacheOperation::Get,
    ]
);
```

## Collecting cache events

We cannot automatically collect cache events in the framework-agnostic version of the package. You can manually add cache events as such:

```php
use Spatie\FlareClient\Enums\CacheOperation;
use Spatie\FlareClient\Enums\CacheResult;

$flare->cache()->record(
    key: 'my-key',
    store: 'redis',
    operation: CacheOperation::Get,
    result: CacheResult::Hit,
);
```

The following combinations of operations and results are possible:

| Operation | Result |
| --------- | ------ |
| `CacheOperation::Get` | `CacheResult::Hit` |
| `CacheOperation::Get` | `CacheResult::Miss` |
| `CacheOperation::Set` | `CacheResult::Success` |
| `CacheOperation::Set` | `CacheResult::Failure` |
| `CacheOperation::Forget` | `CacheResult::Success` |
| `CacheOperation::Forget` | `CacheResult::Failure` |

It is possible to add extra attributes to a cache event as such:

```php
$flare->cache()->record( 
    key: 'my-key',
    store: 'redis',
    operation: CacheOperation::Get,
    result: CacheResult::Hit,
    attributes: [
        'cache.value' => $value,
    ]
);
```