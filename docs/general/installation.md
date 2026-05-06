---
title: Installation 
---

Use the installation guide below to match your PHP version and initialization flow:

<div>
<flare-installation-guide technology="PHP"></flare-installation-guide>
</div>

Great! From now on, Flare will track all errors and exceptions throughout your application.

## Configuring Flare

It is possible to configure the Flare client to suit your needs. You can do this by creating a Flare config object:

```php
use Spatie\FlareClient\FlareConfig;

$config = FlareConfig::make('YOUR-API-KEY')->useDefaults();

$flare = Flare::make($config)->registerFlareHandlers();
```

In the next pages we will discuss the different configuration options available to you, methods you can call on the config object will always be using the `$config` variable, methods that should be called on the Flare object will be using the `$flare` variable.

### Using defaults

During these docs we'll often talk about features being enabled by default this is enabled by calling the `useDefaults` method on the Flare config instance. This method will enable a sensible set of features that should work for most applications. If you want to disable all default features, you can simply omit the `useDefaults` call and enable features manually.

### Setting the application root path

We recommend that you set the application root path. This will help Flare determine the correct file paths in your stack traces.

```php
$config->applicationPath('/path/to/your/application/root');
``` 

## Using an older PHP version?

In the past we've had multiple clients for framework agnostic PHP applications without support for performance monitoring. While these packages are still available, we recommend using the new flare-client-php package for all new projects:

- [spatie/flare-client-php v1](/docs/php/older-packages/flare-client-php-v1): supports PHP 8.0 and later
- [facade/flare-client-php v1](https://github.com/facade/flare-client-php): supports PHP 7.1 until 8.0

## Using Ignition?

If you're maintaining an older project that already uses Ignition, you can keep that setup in place while you work on the app.

For older projects where you still need to add Flare, we recommend installing the standalone `spatie/flare-client-php` or `facade/flare-client-php` package directly instead of adding Ignition.

## Upgrading

You can find more information about the `flare-client-php` upgrade from v1 to v2 [here](https://github.com/spatie/flare-client-php/blob/main/UPGRADING.md).
