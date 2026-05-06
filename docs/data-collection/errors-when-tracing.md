---
title: Errors when tracing 
---


When an error occurs in your application, Flare will receive a full error report with a stack trace and extra context.

When you're tracing, Flare will also automatically track the errors and store them as events on the current span.

It is possible to disable this behaviour by calling `ignoreErrorsWithTraces()` on the Flare config:

```php
$config->ignoreErrorsWithTraces();
``` 