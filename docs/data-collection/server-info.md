---
title: Server info 
---


Flare will automatically keep track of the server information of the server where your application is running. We define four different types of server information:

**Host**

- Hostname
- IP address of the server
- CPU Architecture

**OS**

- The type of operating system
- The name of the operating system
- The version of the operating system
- The description of the operating system

**PHP**

- The PHP version
- The PHP SAPI
- The PHP executable path
- The PHP user

**Composer**

Sets the name and version of your application based upon the root package.

By default, all this information is collected, but you can disable it by calling `ignoreServerInfo()` on the Flare config:

```php
$config->ignoreServerInfo();
```

It is also possible to disable certain groups of information, as such:

```php
$config->ignoreServerInfo(
    host: false,
    os: false,
    php: true,
    composer: true,
);
``` 