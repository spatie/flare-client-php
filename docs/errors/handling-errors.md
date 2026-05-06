---
title: Handling errors 
---


When an error is thrown in an application, the application stops executing, and the error is reported to Flare.
However, there are cases where you might want to handle the error so that the application can continue running. And
the user isn't presented with an error message.

In such cases, reporting the error to Flare might still be helpful, so you'll have a correct overview of what's
going on within your application. We call such errors "handled errors".

When you've caught an error in PHP, it can still be reported to Flare:

```php
try {
    // Code that might throw an exception
} catch (Exception $exception) {
    $flare->reportHandled($exception);
}
```

In Flare, we'll show that the error was handled, and it is possible to filter these errors. You'll find more about filtering errors [here](/docs/flare/errors/searching-errors). 
