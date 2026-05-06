---
title: Requests 
---


Flare can collect information about the requests being made to your application. This includes:

- The request method
- The request URL
- The body size & contents
- The user agent
- The IP address of the user
- The request headers
- The request cookies
- The request query parameters
- The request files
- The request session data

This functionality is enabled by default, but you can disable it by calling `ignoreRequests()` on the Flare config:

```php
$config->ignoreRequests();
```

It is possible to filter out fields from the request body, headers, and the user's IP address. You can read more about this [here](/docs/php/general/censoring-collected-data).

## Collecting requests

In our framework-specific versions of the Flare clients, requests are automatically traced to find performance issues.

In the framework-agnostic version of the package, the user should implement this behaviour:

```php
$flare->tracer->startTrace(); // Starts a new trace

$flare->request()->recordStart();

// Handle your request

$flare->request()->recordEnd();

$flare->tracer->endTrace(); // Send the trace to Flare
```

By default, a Symfony `Request` is built from the globals of your server (think $_GET, $_POST, $_COOKIE, $_SERVER, ...). It is possible to override this behaviour by passing a `Request` object to the `recordStart` method:

```php
use Symfony\Component\HttpFoundation\Request;

$request = Request::create('/blog', 'POST', [
    'title' => 'Hello World',
]);

$flare->request()->recordStart($request);
```

Flare tries to group requests based on URLS. While this is useful, as soon as you have id's within your URL, this can be a problem.

For example, `blog/1` and `blog/2` are the same route; they show a blog post, yet due to their different id's, they will be grouped as different requests.

To group these requests together, you can pass a `route` parameter to the `recordStart` method:

```php
$flare->request()->recordStart(route: 'blog/{id}');
```

Whenever you want to keep track of which class (or closure) handled the request, you can pass an `entryPointClass` parameter to the `recordStart` method:

```php
$flare->request()->recordStart(entryPointClass: BlogController::class);
```

It is also possible to extend the request attributes:

```php
$flare->request()->recordStart(
    attributes: [
        'server.port' => 80
    ]
);
```

When ending the request, an optional response status code and response size can be passed:

```php
$flare->request()->recordEnd(
    responseStatusCode: 200,
    responseBodySize: 1234
);
```

At this point, it is also possible to pass extra attributes to the request:

```php
$flare->request()->recordEnd(
    attributes: [
        'http.response.headers' => json_encode($response->headers),
    ]
);
``` 
