<?php

namespace Spatie\FlareClient;

use Illuminate\Contracts\Container\Container as IlluminateContainer;
use Illuminate\Support\Arr;
use Spatie\ErrorSolutions\Contracts\SolutionProviderRepository as SolutionProviderRepositoryContract;
use Spatie\FlareClient\Context\ContextProviderDetector;
use Spatie\FlareClient\Contracts\Recorder;
use Spatie\FlareClient\Exporters\JsonExporter;
use Spatie\FlareClient\FlareMiddleware\AddRecordedEntries;
use Spatie\FlareClient\FlareMiddleware\ContainerAwareFlareMiddleware;
use Spatie\FlareClient\FlareMiddleware\RecordingMiddleware;
use Spatie\FlareClient\Http\Client;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Sampling\Sampler;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\Container;
use Spatie\FlareClient\Support\PhpStackFrameArgumentsFixer;
use Spatie\FlareClient\Support\TraceLimits;

class FlareProvider
{
    public function __construct(
        protected FlareConfig $config,
        protected Container|IlluminateContainer $container
    ) {
    }

    public function register(): void
    {
        $this->container ??= Container::instance();

        $this->container->singleton(Sender::class, fn () => new $this->config->sender(
            ...$this->config->senderConfig
        ));

        $this->container->singleton(Api::class, fn () => new Api(
            apiToken: $this->config->apiToken,
            baseUrl: $this->config->baseUrl,
            timeout: $this->config->timeout,
            sender: $this->container->get(Sender::class),
            sendReportsImmediately: $this->config->sendReportsImmediately
        ));

        $this->container->singleton(Sampler::class, fn () => new $this->config->sampler(
            ...$this->config->samplerConfig
        ));

        $this->container->singleton(JsonExporter::class, fn () => new JsonExporter());

        $this->container->singleton(BackTracer::class, fn () => new BackTracer());

        $this->container->singleton(Resource::class, fn () => Resource::build(
            $this->config->applicationName,
            $this->config->applicationVersion
        ));

        $this->container->singleton(Scope::class, fn () => Scope::build());

        $this->container->singleton(Tracer::class, fn () => new Tracer(
            api: $this->container->get(Api::class),
            exporter: $this->container->get(JsonExporter::class),
            backTracer: $this->container->get(BackTracer::class),
            resource: $this->container->get(Resource::class),
            scope: $this->container->get(Scope::class),
            limits: $this->config->traceLimits ?? TraceLimits::defaults(),
        ));

        $this->container->singleton(SolutionProviderRepositoryContract::class, function () {
            /** @var SolutionProviderRepositoryContract $repository */
            $repository = new $this->config->solutionsProviderRepository;

            $repository->registerSolutionProviders($this->config->solutionsProviders);

            return $repository;
        });

        foreach ($this->config->recorders as $recorder => $config) {
            $this->container->singleton($recorder, fn () => $recorder::initialize($this->container, $config));
        }

        foreach ($this->config->middleware as $middleware => $config) {
            $this->container->singleton($middleware, fn () => $middleware::initialize($this->container, $config));
        }

        $this->container->singleton(Flare::class, function () {
            $recorders = array_combine(
                array_keys($this->config->recorders),
                array_map(
                    fn ($recorder) => $this->container->get($recorder),
                    array_keys($this->config->recorders)
                )
            );

            $middleware = array_map(
                fn ($middleware) => $this->container->get($middleware),
                array_keys($this->config->middleware)
            );

            array_unshift($middleware, new AddRecordedEntries($recorders));

            return new Flare(
                container: $this->container,
                api: $this->container->get(Api::class),
                tracer: $this->container->get(Tracer::class),
                applicationPath: $this->config->applicationPath,
                middleware: $middleware,
                recorders: $recorders,
                applicationName: $this->config->applicationName,
                applicationVersion: $this->config->applicationVersion,
                stage: $this->config->applicationStage,
                reportErrorLevels: $this->config->reportErrorLevels,
                filterExceptionsCallable: $this->config->filterExceptionsCallable,
                filterReportsCallable: $this->config->filterReportsCallable,
                argumentReducers: $this->config->argumentReducers,
                withStackFrameArguments: $this->config->withStackFrameArguments,
            );
        });

        if ($this->config->forcePHPStackFrameArgumentsIniSetting) {
            (new PhpStackFrameArgumentsFixer())->enable();
        }
    }

    public function boot(): void
    {
        foreach ($this->config->recorders as $recorder => $config) {
            $this->container->get($recorder)->start();
        }
    }
}
