<?php

namespace Spatie\FlareClient;

use Illuminate\Contracts\Container\Container as IlluminateContainer;
use Spatie\ErrorSolutions\Contracts\SolutionProviderRepository as SolutionProviderRepositoryContract;
use Spatie\ErrorSolutions\SolutionProviderRepository;
use Spatie\FlareClient\Context\BaseContextProviderDetector;
use Spatie\FlareClient\Context\ContextProviderDetector;
use Spatie\FlareClient\Contracts\Recorder;
use Spatie\FlareClient\FlareMiddleware\ContainerAwareFlareMiddleware;
use Spatie\FlareClient\FlareMiddleware\RecordingMiddleware;
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
    public function __construct(
        protected FlareConfig $config,
        protected Container|IlluminateContainer $container
    ) {
    }

    public function register(): void
    {
        $this->container ??= Container::instance();

        $this->container->singleton(Client::class, fn () => $this->config->client ?? new Client(
            apiToken: $this->config->apiToken,
            baseUrl: $this->config->baseUrl,
            timeout: $this->config->timeout,
            sender: $this->container->get(Sender::class)
        ));

        $this->container->singleton(Api::class, fn () => new Api(
            client: $this->container->get(Client::class),
            sendReportsImmediately: $this->config->sendReportsImmediately
        ));

        $this->container->singleton(Sender::class, fn () => new $this->config->sender);

        $this->container->singleton(JsonExporter::class, fn () => new JsonExporter());

        $this->container->singleton(BackTracer::class, fn () => new BackTracer());

        $this->container->singleton(Resource::class, fn () => Resource::build(
            $this->config->applicationName,
            $this->config->applicationVersion
        ));

        $this->container->singleton(Scope::class, fn () => Scope::build());

        $this->container->singleton(Tracer::class, fn () => new Tracer(
            client: $this->container->get(Client::class),
            exporter: $this->container->get(JsonExporter::class),
            backTracer: $this->container->get(BackTracer::class),
            resource: $this->container->get(Resource::class),
            scope: $this->container->get(Scope::class)
        ));

        $this->container->singleton(ContextProviderDetector::class, fn () => new $this->config->contextProviderDetector);

        $this->container->singleton(SolutionProviderRepositoryContract::class, function () {
            /** @var SolutionProviderRepositoryContract $repository */
            $repository = new $this->config->solutionsProviderRepository;

            $repository->registerSolutionProviders($this->config->solutionsProviders);

            return $repository;
        });

        foreach ($this->config->middleware as $middleware) {
            if ($middleware instanceof ContainerAwareFlareMiddleware) {
                $middleware->register($this->container);
            }

            if ($middleware instanceof RecordingMiddleware) {
                $middleware->setupRecording(
                    fn (string $recorderClass, callable $recorderInitializer, callable $recorderSetter) => $this->container->singleton(
                        $recorderClass,
                        fn () => $recorderInitializer($this->container)
                    )
                );
            }
        }

        $this->container->singleton(Flare::class, fn () => new Flare(
            container: $this->container,
            api: $this->container->get(Api::class),
            client: $this->container->get(Client::class),
            tracer: $this->container->get(Tracer::class),
            applicationPath: $this->config->applicationPath,
            contextProviderDetector: $this->container->get(ContextProviderDetector::class),
            middleware: $this->config->middleware,
            applicationName: $this->config->applicationName,
            applicationVersion: $this->config->applicationVersion,
            stage: $this->config->applicationStage,
            reportErrorLevels: $this->config->reportErrorLevels,
            filterExceptionsCallable: $this->config->filterExceptionsCallable,
            filterReportsCallable: $this->config->filterReportsCallable,
            argumentReducers: $this->config->argumentReducers,
            withStackFrameArguments: $this->config->withStackFrameArguments,
        ));

        if ($this->config->forcePHPStackFrameArgumentsIniSetting) {
            (new PhpStackFrameArgumentsFixer())->enable();
        }
    }

    public function boot(): Flare
    {
        $flare = $this->container->get(Flare::class);

        foreach ($this->config->middleware as $middleware) {
            if ($middleware instanceof ContainerAwareFlareMiddleware) {
                $middleware->boot($this->container);
            }

            if ($middleware instanceof RecordingMiddleware) {
                $middleware->setupRecording(
                    function (string $recorderClass, callable $recorderInitializer, callable $recorderSetter) {
                        /** @var Recorder $recorder */
                        $recorder = $this->container->get($recorderClass);

                        $recorderSetter($recorder);

                        $recorder->start();
                    }
                );
            }
        }

        return $flare;
    }
}
