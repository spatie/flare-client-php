---
title: External HTTP requests 
---


Flare can collect information about the external HTTP requests that are being made from your application. This includes:

- The request method
- The request URL
- The request body size
- The request headers
- The response status code
- The response body size
- The response headers

This functionality is enabled by default, but you can disable it by calling `ignoreExternalRequests()` on the Flare config:

```php
$config->ignoreExternalHttp();
```

You can configure the maximum number of external HTTP requests tracked while collecting data in the case of an error as follows:

```php
$config->collectExternalHttp(maxItemsWithErrors: 10);
```

It is possible to filter out headers. You can read more about this [here](/docs/php/general/censoring-collected-data).

## Collecting Guzzle HTTP requests

Flare can automatically collect Guzzle HTTP requests. This is done by using a middleware that will be added to the Guzzle client:

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Spatie\FlareClient\Recorders\ExternalHttpRecorder\Guzzle\FlareMiddleware;

$stack = HandlerStack::create();

$stack->push(new FlareMiddleware($flare));

$client = new Client([
    'handler' => $stack,
]);
```

You can also use the `FlareHandlerStack`, which requires less code:

```php
use Spatie\FlareClient\Recorders\ExternalHttpRecorder\Guzzle\FlareHandlerStack;

$client = new Client([
    'handler' => new FlareHandlerStack($flare)
]);
```

## Manually collecting external HTTP requests

We cannot automatically collect external HTTP requests in the framework-agnostic version of the package. You can manually add external HTTP requests as such:

```php
$flare->externalHttp()->recordSending(
    url: 'https://example.com',
    method: 'POST',
);
```

You can also add the size of the request body and the headers:

```php
$flare->externalHttp()->recordSending(
    url: 'https://example.com',
    method: 'POST',
    bodySize: 1234,
    headers: $request->headers,
);
```

It is also possible to add extra attributes to the request:

```php
$flare->externalHttp()->recordSending(
    url: 'https://example.com',
    method: 'POST',
    attributes: [
        'http.request.cookies' => json_encode($request->cookies),
    ]
);
```

When the request ends, you can call the `recordReceived` method:

```php
$flare->externalHttp()->recordReceived(
    statusCode: 200,
);
```

Here you can also add the size of the response body and the headers:

```php
$flare->externalHttp()->recordReceived(
    responseStatusCode: 200,
    responseBodySize: 1234,
    responseHeaders: $response->headers,
);
```
Or add extra attributes:

```php
$flare->externalHttp()->recordReceived(
    statusCode: 200,
    attributes: [
        'server.port' => 80,
    ]
);
```

When the request failed due to a connection error, you can call the `recordConnectionFailed` method:

```php
$flare->externalHttp()->recordConnectionFailed(
    'Connection refused',
);
```
