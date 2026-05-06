---
title: Reporting errors 
---


PHP defines two types of "things that might go wrong within your application":

- Exceptions
- Errors

While the first is the most common, the second is also a valid way of handling errors in PHP. A fatal error is an example of a PHP error that cannot be caught by a try-catch block.

The Flare client can handle exceptions and errors; it wraps errors within `ErrorException` instances and sends them to Flare.

It is possible to set the minimum error level that will be sent to Flare. By default, all errors are sent to Flare. You can change this by calling `reportErrorLevels` on the Flare config:

```php
$config->reportErrorLevels(E_ALL & ~E_NOTICE); // Will send all errors except E_NOTICE errors
```

Small note: We will always refer to errors throughout the documentation, but this also includes exceptions. While throwables as a word is more accurate in this context, we want to keep it simple for users new to PHP and stick with the error naming for all throwables. 