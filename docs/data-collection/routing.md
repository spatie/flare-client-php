---
title: Routing 
---

Flare can collect information about the routing of your application. This includes:

- The routing process to find a route to a controller
- Middleware that is executed

This functionality is enabled by default, but you can disable it by calling `ignoreRouting()` on the Flare config:

```php
$config->ignoreRequests();
```

## Routing stages

Flare defines five routing stages:

- Global before middleware: middleware executed for every route within your application on the request
- Routing: the process of finding a route to a controller
- Before middleware: middleware executed for the current route on the request
- After middleware: middleware executed for the current route on the response
- Global after middleware: middleware executed for every route within your application on the response

When your application doesn't have a concept of global middleware, you can still keep the following stages:

- Routing
- Before middleware
- After middleware

Lastly, it is totally valid to not have any middleware at all. In that case, you can keep the routing stage.

## Recording routing stages

We cannot automatically record the routing stages in our framework-agnostic version of the Flare client. You can do this manually as such:

**Global before middleware**

```php
$flare->routing()->recordGlobalBeforeMiddlewareStart(time: 10);

//middleware executed for every route within your application on the request

$flare->routing()->recordGlobalBeforeMiddlewareEnd(time: 20);
```

When you don't have a specific start point for this stage, you can call the following at the end of the stage:

```php
$flare->routing()->recordGlobalBeforeMiddleware(start: 10, end: 20);
```

**Routing**


```php
$flare->routing()->recordRoutingStart(time: 30);

// The process of finding a route to a controller

$flare->routing()->recordRoutingEnd(time: 40);
```

When you don't have a specific start point for this stage, you can call the following at the end of the stage:

```php
$flare->routing()->recordRouting(start: 30, end: 40);
```

**Before middleware**

```php
$flare->routing()->recordBeforeMiddlewareStart(time: 50);

//middleware executed for the current route on the request

$flare->routing()->recordBeforeMiddlewareEnd(time: 60);
```

When you don't have a specific start point for this stage, you can call the following at the end of the stage:

```php
$flare->routing()->recordBeforeMiddleware(start: 50, end: 60);
```

**After middleware**

```php
$flare->routing()->recordAfterMiddlewareStart(time: 70);

//middleware executed for the current route on the response

$flare->routing()->recordAfterMiddlewareEnd(time: 80);
```

When you don't have a specific start point for this stage, you can call the following at the end of the stage:

```php
$flare->routing()->recordAfterMiddleware(start: 70, end: 80);
```

**Global after middleware**

```php
$flare->routing()->recordGlobalAfterMiddlewareStart(time: 90);

//middleware executed for every route within your application on the response
$flare->routing()->recordGlobalAfterMiddlewareEnd(time: 100);
```

When you don't have a specific start point for this stage, you can call the following at the end of the stage:

```php
$flare->routing()->recordGlobalAfterMiddleware(start: 90, end: 100);
```
