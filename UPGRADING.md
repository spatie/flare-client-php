# Upgrading

Because there are many breaking changes an upgrade is not that easy. There are many edge cases this guide does not
cover. We accept PRs to improve this guide.

## From v1 to v2

- The `anonymizeIp()` method was renamed to `censorClientIps()` and should now be called on Flare config
- The `censorRequestBodyFields()` method was renamed to `censorBodyFields()` and should now be called on Flare config
- We've changed how glows are added (MessageLevels are now enums which were slightly renamed):

```php
$flare->glow('This is a message from glow!', MessageLevels::DEBUG); // Old way

$flare->glow()->record('This is a message from glow!', MessageLevels::Debug); // New way
```
