---
title: Application info 
---


## Application Name

You can configure Flare to add the name of your application to all sent exceptions and traces:

```php
$config->applicationName("Flare");
```

It is also possible to use a closure to set the name number:

```php
$config->applicationName(function() {
   return 'Flare';
});
```

## Application Version

You can configure Flare to add a version number to all sent exceptions and traces:

```php
$config->applicationVersion("1.0");
```

It is also possible to use a closure to set the version number:

```php
$config->applicationVersion(function() {
   return '1.0' ; // return your version number
});
```

## Application stage

You can configure Flare to add a stage(production, development, staging, ...) to all sent exceptions and traces:

```php
$config->applicationStage("staging");
```

```php
$config->applicationStage(function() {
   return 'staging';
});
```