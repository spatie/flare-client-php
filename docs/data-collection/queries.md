---
title: Queries 
---


Flare can collect information about the queries being executed in your application. This includes:

- The query
- The query bindings
- The database name
- The database driver

This functionality is enabled by default, but you can disable it by calling `ignoreQueries()` on the Flare config:

```php
$config->ignoreQueries();
```

You can configure the maximum number of queries tracked while collecting data in the case of an error as such:

```php
$config->collectQueries(maxItemsWithErrors: 10);
```

## Collecting queries

We cannot automatically collect queries in the framework-agnostic version of the package. You can manually add queries as such:

```php
use Spatie\FlareClient\Time\TimeHelper;

$flare->query()->record(
    query: 'SELECT * FROM users WHERE id = ?',
    duration: TimeHelper::milliseconds(300),
    bindings: [1],
    databaseName: 'mysql',
    driverName: 'mysql',
);
```

The duration should be in milliseconds. When you don't want to pass the duration, you can use the `recordStart` and `recordEnd` methods:

```php
$flare->query()->recordStart(
    query: 'SELECT * FROM users WHERE id = ?',
    bindings: [1],
    databaseName: 'mysql',
    driverName: 'mysql',
);

// Do your query

$flare->query()->recordEnd();
```

It is always possible to add extra attributes to the query:

```php
use Spatie\FlareClient\Time\TimeHelper;

$flare->query()->record(
    query: 'SELECT * FROM users WHERE id = ?',
    duration: TimeHelper::millisecond(300),
    bindings: [1],
    databaseName: 'mysql',
    driverName: 'mysql',
    attributes: [
        'database.connection' => 'mysql',
    ]
);
```

## Finding the origin

Database queries sometimes become a bit tricky to debug. You can find the origin of the query by setting the `findOrigin` parameter to `true`:

```php
$config->collectQueries(findOrigin: true);
```

Now, every query will include the file and line number where the query was executed.

If you only want to find the origins of slow queries, you can pass a `findOriginThreshold` parameter to the `collectQueries` method in milliseconds:

```php
$config->collectQueries(findOriginThreshold: TimeHelper::milliseconds(100));
```

Now, only queries that take longer than 100 milliseconds will include the file and line number where the query was executed. By default, queries slower than 300 milliseconds will be collected.

Be careful not to set this threshold too low, as it can cause a lot of overhead in your application.

Small note, don't set `findOrigin` when using `findOriginThreshold`, as this will cause the Flare client to always look for the origin of the query, even if it is not slow.

## Bindings

When you're passing bindings alongside your query, Flare will automatically collect them. You can also pass the bindings manually.

You can either not pass bindings at all, or disable sending query bindings as such:

```php
$config->collectQueries(includeBindings: false);
``` 