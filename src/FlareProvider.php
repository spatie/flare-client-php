<?php

namespace Spatie\FlareClient;

use Closure;
use Illuminate\Contracts\Container\Container as IlluminateContainer;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\ErrorSolutions\Contracts\SolutionProviderRepository as SolutionProviderRepositoryContract;
use Spatie\FlareClient\Contracts\Recorders\Recorder;
use Spatie\FlareClient\Enums\SamplingType;
use Spatie\FlareClient\Exporters\JsonExporter;
use Spatie\FlareClient\FlareMiddleware\AddRecordedEntries;
use Spatie\FlareClient\Recorders\ThrowableRecorder\ThrowableRecorder;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Sampling\Sampler;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\Container;
use Spatie\FlareClient\Support\PhpStackFrameArgumentsFixer;
use Spatie\FlareClient\Support\Redactor;
use Spatie\FlareClient\Support\SentReports;
use Spatie\FlareClient\Support\Telemetry;
use Spatie\FlareClient\Support\TraceLimits;
use Throwable;

class FlareProvider
{
    /**
     * @param Closure(Container|IlluminateContainer, class-string<Recorder>, array):void|null $registerRecorderAndMiddlewaresCallback
     */
    public function __construct(
        protected FlareConfig $config,
        protected Container|IlluminateContainer $container,
        protected ?Closure $registerRecorderAndMiddlewaresCallback = null,
    ) {
        $this->registerRecorderAndMiddlewaresCallback ??= $this->defaultRegisterRecordersAndMiddlewaresCallback();
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

        $this->container->singleton(BackTracer::class, fn () => new BackTracer(
            $this->config->applicationPath
        ));

        $this->container->singleton(Resource::class, fn () => (new Resource(
            $this->config->applicationName,
            $this->config->applicationVersion,
            $this->config->applicationStage,
            Telemetry::NAME,
            Telemetry::VERSION
        ))->host()->operatingSystem()->processRuntime());

        $this->container->singleton(Scope::class, fn () => new Scope(
            Telemetry::NAME,
            Telemetry::VERSION
        ));

        $this->container->singleton(Tracer::class, fn () => new Tracer(
            api: $this->container->get(Api::class),
            exporter: $this->container->get(JsonExporter::class),
            limits: $this->config->traceLimits ?? new TraceLimits(),
            resource: $this->container->get(Resource::class),
            scope: $this->container->get(Scope::class),
            sampler: $this->container->get(Sampler::class),
            samplingType: $this->config->trace
                ? SamplingType::Waiting
                : SamplingType::Disabled,
        ));

        $this->container->singleton(ArgumentReducers::class, fn () => match (true) {
            $this->config->argumentReducers === null => null,
            is_array($this->config->argumentReducers) => ArgumentReducers::create($this->config->argumentReducers),
            default => $this->config->argumentReducers,
        });

        $this->container->singleton(SolutionProviderRepositoryContract::class, function () {
            /** @var SolutionProviderRepositoryContract $repository */
            $repository = new $this->config->solutionsProviderRepository;

            $repository->registerSolutionProviders($this->config->solutionsProviders);

            return $repository;
        });

        $this->container->singleton(SentReports::class, fn () => new SentReports());

        $this->container->singleton(ThrowableRecorder::class, fn () => new ThrowableRecorder(
            $this->container->get(Tracer::class),
        ));

        $this->container->singleton(Redactor::class, fn() => new Redactor(
            censorClientIps: $this->config->censorClientIps,
            censorHeaders: $this->config->censorHeaders,
            censorBodyFields: $this->config->censorBodyFields,
        ));

        foreach ($this->config->recorders as $recorderClass => $config) {
            ($this->registerRecorderAndMiddlewaresCallback)($this->container, $recorderClass, $config);
        }

        foreach ($this->config->middleware as $middlewareClass => $config) {
            ($this->registerRecorderAndMiddlewaresCallback)($this->container, $middlewareClass, $config);
        }

        $this->container->singleton(Flare::class, function () {
            $recorders = array_combine(
                array_map(
                /** @var class-string<Recorder> $recorder */
                    fn ($recorder) => is_string($recorder::type()) ? $recorder::type() : $recorder::type()->value,
                    array_keys($this->config->recorders)
                ),
                array_map(
                    function ($recorder) {
                        try {
                            return $this->container->get($recorder);
                        } catch (Throwable $t) {
                            dd($t); // TODO: remove
                        }
                    },
                    array_keys($this->config->recorders)
                )
            );

            $middleware = array_map(
                fn ($middleware) => $this->container->get($middleware),
                array_keys($this->config->middleware)
            );

            array_unshift($middleware, new AddRecordedEntries($recorders));

            return new Flare(
                api: $this->container->get(Api::class),
                tracer: $this->container->get(Tracer::class),
                backTracer: $this->container->get(BackTracer::class),
                sentReports: $this->container->get(SentReports::class),
                middleware: $middleware,
                recorders: $recorders,
                throwableRecorder: $this->config->traceThrowables
                    ? $this->container->get(ThrowableRecorder::class)
                    : null,
                applicationPath: $this->config->applicationPath,
                reportErrorLevels: $this->config->reportErrorLevels,
                filterExceptionsCallable: $this->config->filterExceptionsCallable,
                filterReportsCallable: $this->config->filterReportsCallable,
                argumentReducers: $this->container->get(ArgumentReducers::class),
                withStackFrameArguments: $this->config->withStackFrameArguments,
                resource: $this->container->get(Resource::class),
                scope: $this->container->get(Scope::class),
            );
        });

        if ($this->config->forcePHPStackFrameArgumentsIniSetting) {
            (new PhpStackFrameArgumentsFixer())->enable();
        }
    }

    public function boot(): void
    {
        $this->container->get(Flare::class)->startRecorders();
    }

    protected function defaultRegisterRecordersAndMiddlewaresCallback(): Closure
    {
        return fn (Container|IlluminateContainer $container, string $class, array $config) => $container->singleton(
            $class,
            function () use ($container, $config, $class) {
                return method_exists($class, 'register')
                    ? $class::register($container, $config)()
                    : new $class;
            }
        );
    }
}
