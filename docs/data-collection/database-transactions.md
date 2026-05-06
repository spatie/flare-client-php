---
title: Database transactions 
---


Flare can collect information about the database transactions being executed in your application. This includes:

- Whether the transaction was committed or rolled back

This functionality is enabled by default, but you can disable it by calling `ignoreTransactions()` on the Flare config:

```php
$config->ignoreTransactions();
```

You can configure the maximum number of transactions tracked while collecting data in the case of an error as such:

```php
$config->collectTransactions(maxItemsWithErrors: 10);
```

## Collecting transactions

We cannot automatically collect transactions in the framework-agnostic version of the package. You can manually add transactions as such:

```php
$flare->transaction()->recordBegin();

// Do your transaction

$flare->transaction()->recordCommit();
```

In the case of a rollback, you can call the `recordRollback` method:

```php
$flare->transaction()->recordBegin();

// Do your transaction

$flare->transaction()->recordRollback();
```

It is always possible to add extra attributes to the transaction:

```php
$flare->transaction()->recordBegin(
    attributes: [
        'database.connection' => 'mysql',
    ]
);
```

Or when committing:

```php
$flare->transaction()->recordCommit(
    attributes: [
        'database.connection' => 'mysql',
    ]
);
```

Or when rolling back:

```php
$flare->transaction()->recordRollback(
    attributes: [
        'database.connection' => 'mysql',
    ]
);
``` 