# Upgrading

Because there are many breaking changes an upgrade is not that easy. There are many edge cases this guide does not
cover. We accept PRs to improve this guide.

## From v1 to v2

Version two of the package has been a complete rewrite, we've added some interesting points in this upgrade guide but advise you to read the docs again.

- The `anonymizeIp()` method was renamed to `censorClientIps()` and should now be called on Flare config object
- The `censorRequestBodyFields()` method was renamed to `censorBodyFields()` and should now be called on Flare config object
- The `reportErrorLevels` method should now be called on Flare config object
- The `overrideGrouping` method should now be called on Flare config object
- The `$flare->context()` method works a bit different now, the concept of groups has been removed. A single context item still can be added like this:

```php
$flare->context('key', 'value'); // Single item
```

Multiple context items can be added like this:

```php
$flare->context([
    'key' => 'value',
    'key2' => 'value2',
]);
```
- The `group` method to add context data has been removed, you should just use the `context()` method
- We've changed how glows are added (MessageLevels is now an enum and slightly renamed):

```php
$flare->glow('This is a message from glow!', MessageLevels::DEBUG); // Old way

$flare->glow()->record('This is a message from glow!', MessageLevels::Debug); // New way
```

- The `argumentReducers()` method has been removed, you should use the `collectStackFrameArguments` method on the Flare config object
- It is still possible to add custom middleware. We did remove the possibility to add middleware inline with a closure and adding a middleware class must now be done like this on the Flare config object:

```php
$config->collectFlareMiddleware([
    MyMiddleware::class => [],
]);
```

- All the recorders and middleware provided by the package are rewritten or removed, if you extended any of these please check them.
