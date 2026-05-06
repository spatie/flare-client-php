---
title: Dumps 
---


Flare collects information about the dumps that are being executed in your application.

You can disable this behaviour by calling `ignoreDumps()` on the Flare config:

```php
$config->ignoreDumps();
```

You can configure the maximum number of dumps tracked while collecting data in the case of an error as such:

```php
$config->collectDumps(maxItemsWithErrors: 10);
```

## Collecting dumps

Dumps will automatically be collected; there's no need to manually add them. 