---
title: Customise the error report 
---


Before Flare receives the data collected from your local exception, we allow you to call custom middleware methods.

These methods retrieve the report factory that will eventually be sent to Flare and allow you to add custom information to that report.

You can create a Flare middleware as such:

```php
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\ReportFactory;

class MyMiddleware implements FlareMiddleware
{
    public function handle(ReportFactory $report, Closure $next): ReportFactory;
    {
        $report->handled(true);

        return $next($report);
    }
}
```

You need to register the middleware as follows:

```php
$config->collectFlareMiddleware([
    MyMiddleware::class => [],
]);
```

You can also pass additional options to the middleware as such:

```php
$config->collectFlareMiddleware([
    MyMiddleware::class => [
        'mark_every_error_handled' => false,
    ],
]);
```

The middleware can get these config values as follows:

```php
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\ReportFactory;

class MyMiddleware implements FlareMiddleware
{
    public function __construct(
        protected bool $markEveryErrorHandled = false,
    ) {}
    
    public static function register(Container $container, array $config): Closure
    {
        return fn() => new self(
            $config['mark_every_error_handled'] ?? false,
        );
    }

    public function handle(ReportFactory $report, Closure $next): ReportFactory;
    {
        $report->handled($this->config['mark_every_error_handled']);

        return $next($report);
    }
}
```

Since the framework-agnostic version of Flare does not have an auto wiring container, you'll need to register the middleware manually. During the registration, you'll receive a config array with the options that were passed to the middleware. 