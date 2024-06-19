<?php

namespace Spatie\FlareClient;

use Illuminate\Contracts\Container\Container as IlluminateContainer;
use Spatie\ErrorSolutions\Contracts\SolutionProviderRepository as SolutionProviderRepositoryContract;
use Spatie\ErrorSolutions\SolutionProviderRepository;
use Spatie\FlareClient\Context\BaseContextProviderDetector;
use Spatie\FlareClient\Context\ContextProviderDetector;
use Spatie\FlareClient\FlareMiddleware\ContainerAwareFlareMiddleware;
use Spatie\FlareClient\Http\Client;
use Spatie\FlareClient\Performance\Exporters\JsonExporter;
use Spatie\FlareClient\Performance\Resources\Resource;
use Spatie\FlareClient\Performance\Scopes\Scope;
use Spatie\FlareClient\Performance\Support\BackTracer;
use Spatie\FlareClient\Performance\Tracer;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Support\Container;
use Spatie\FlareClient\Support\PhpStackFrameArgumentsFixer;

class FlareProvider
{
    public function register(
        FlareConfig $config,
        null|Container|IlluminateContainer $container = null
    ): void {
        $container ??= Container::instance();

        $container->singleton(Client::class, fn () => $config->client ?? new Client(
            apiToken: $config->apiToken,
            baseUrl: $config->baseUrl,
            timeout: $config->timeout,
            sender: $container->get(Sender::class)
        ));

        $container->singleton(Api::class, fn () => new Api(
            client: $container->get(Client::class),
            sendReportsImmediately: $config->sendReportsImmediately
        ));

        $container->singleton(Sender::class, fn () => new $config->sender);

        $container->singleton(JsonExporter::class, fn () => new JsonExporter());

        $container->singleton(BackTracer::class, fn () => new BackTracer());

        $container->singleton(Resource::class, fn () => Resource::build(
            $config->applicationName,
            $config->applicationVersion
        ));

        $container->singleton(Scope::class, fn () => Scope::build());

        $container->singleton(Tracer::class, fn () => new Tracer(
            client: $container->get(Client::class),
            exporter: $container->get(JsonExporter::class),
            backTracer: $container->get(BackTracer::class),
            resource: $container->get(Resource::class),
            scope: $container->get(Scope::class)
        ));

        $container->singleton(ContextProviderDetector::class, fn () => new BaseContextProviderDetector());

        $container->singleton(SolutionProviderRepositoryContract::class, fn () => new SolutionProviderRepository($config->solutionsProviders));

        foreach ($config->middleware as $middleware) {
            if (! $middleware instanceof ContainerAwareFlareMiddleware) {
                continue;
            }

            $middleware->register($container);
        }

        $container->singleton(Flare::class, fn () => new Flare(
            container: $container,
            api: $container->get(Api::class),
            client: $container->get(Client::class),
            tracer: $container->get(Tracer::class),
            applicationPath: $config->applicationPath,
            contextProviderDetector: $container->get(ContextProviderDetector::class),
            middleware: $config->middleware,
            applicationName: $config->applicationName,
            applicationVersion: $config->applicationVersion,
            stage: $config->applicationStage,
            reportErrorLevels: $config->reportErrorLevels,
            filterExceptionsCallable: $config->filterExceptionsCallable,
            filterReportsCallable: $config->filterReportsCallable,
            argumentReducers: $config->argumentReducers,
            withStackFrameArguments: $config->withStackFrameArguments
        ));

        if ($config->forcePHPStackFrameArgumentsIniSetting) {
            (new PhpStackFrameArgumentsFixer())->enable();
        }
    }

}
