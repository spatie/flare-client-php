<?php

namespace Spatie\FlareClient;

use Closure;
use Illuminate\Contracts\Container\Container as IlluminateContainer;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\ErrorSolutions\Contracts\SolutionProviderRepository as SolutionProviderRepositoryContract;
use Spatie\FlareClient\AttributesProviders\UserAttributesProvider;
use Spatie\FlareClient\Contracts\Recorders\Recorder;
use Spatie\FlareClient\Enums\CollectType;
use Spatie\FlareClient\Enums\SamplingType;
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
use Spatie\FlareClient\Support\StacktraceMapper;
use Spatie\FlareClient\Support\Telemetry;
use Spatie\FlareClient\Support\TraceLimits;
use Spatie\FlareClient\TraceExporters\TraceExporter;

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
            $this->config->senderConfig
        ));

        $this->container->singleton(Api::class, fn () => new Api(
            apiToken: $this->config->apiToken ?? 'No Api Token provided',
            baseUrl: $this->config->baseUrl,
            sender: $this->container->get(Sender::class),
            sendReportsImmediately: $this->config->sendReportsImmediately
        ));

        $this->container->singleton(Sampler::class, fn () => new $this->config->sampler(
            $this->config->samplerConfig
        ));

        $this->container->singleton(TraceExporter::class, fn () => new $this->config->traceExporter);

        $this->container->singleton(StacktraceMapper::class, fn () => new $this->config->stacktraceMapper);

        $this->container->singleton(BackTracer::class, fn () => new BackTracer(
            $this->config->applicationPath
        ));

        $this->container->singleton(Tracer::class, fn () => new Tracer(
            api: $this->container->get(Api::class),
            exporter: $this->container->get(TraceExporter::class),
            limits: $this->config->traceLimits ?? new TraceLimits(),
            resource: $this->container->get(Resource::class),
            scope: $this->container->get(Scope::class),
            sampler: $this->container->get(Sampler::class),
            configureSpansCallable: $this->config->configureSpansCallable,
            configureSpanEventsCallable: $this->config->configureSpanEventsCallable,
            samplingType: $this->config->trace
                ? SamplingType::Waiting
                : SamplingType::Disabled,
        ));

        $this->container->singleton(SentReports::class);

        $this->container->singleton(ThrowableRecorder::class, fn () => new ThrowableRecorder(
            $this->container->get(Tracer::class),
        ));

        $this->container->singleton(Redactor::class, fn () => new Redactor(
            censorClientIps: $this->config->censorClientIps,
            censorHeaders: $this->config->censorHeaders,
            censorBodyFields: $this->config->censorBodyFields,
        ));

        $this->container->singleton(UserAttributesProvider::class, $this->config->userAttributesProvider);

        [
            'middlewares' => $middlewares,
            'recorders' => $recorders,
            'solutionProviders' => $solutionProviders,
            'resourceModifiers' => $resourceModifiers,
            'collectStackFrameArguments' => $collectStackFrameArguments,
            'argumentReducers' => $argumentReducers,
            'forcePHPStackFrameArgumentsIniSetting' => $forcePHPStackFrameArgumentsIniSetting,
        ] = (new $this->config->collectsResolver)->execute($this->config->collects);

        $this->container->singleton(ArgumentReducers::class, fn () => match (true){
            $collectStackFrameArguments === false => ArgumentReducers::create([]),
            is_array($argumentReducers) => ArgumentReducers::create($argumentReducers),
            default => $argumentReducers,
        });

        $this->container->singleton(SolutionProviderRepositoryContract::class, function () use ($solutionProviders) {
            /** @var SolutionProviderRepositoryContract $repository */
            $repository = new $this->config->solutionsProviderRepository;

            $repository->registerSolutionProviders($solutionProviders);

            return $repository;
        });

        $this->container->singleton(Resource::class, function () use ($resourceModifiers) {
            $resource = new Resource(
                $this->config->applicationName,
                $this->config->applicationVersion,
                $this->config->applicationStage,
                Telemetry::NAME,
                Telemetry::VERSION
            );

            foreach ($resourceModifiers as $resourceModifier) {
                $resource = $resourceModifier($resource);
            }

            if ($this->config->configureResourceCallable) {
                ($this->config->configureResourceCallable)($resource);
            }

            return $resource;
        });

        $this->container->singleton(Scope::class, function () {
            $scope = new Scope(
                Telemetry::NAME,
                Telemetry::VERSION
            );

            if ($this->config->configureScopeCallable) {
                ($this->config->configureScopeCallable)($scope);
            }

            return $scope;
        });


        $middlewares = array_merge(
            $middlewares,
            $this->config->middleware
        );

        $recorders = array_merge(
            $recorders,
            $this->config->recorders
        );

        foreach ($recorders as $recorderClass => $config) {
            ($this->registerRecorderAndMiddlewaresCallback)($this->container, $recorderClass, $config);
        }

        foreach ($middlewares as $middlewareClass => $config) {
            ($this->registerRecorderAndMiddlewaresCallback)($this->container, $middlewareClass, $config);
        }

        $this->container->singleton(Flare::class, function () use ($collectStackFrameArguments, $middlewares, $recorders) {
            $recorders = array_combine(
                array_map(
                    function ($recorder) {
                        /** @var class-string<Recorder> $recorder */
                        $recorderType = $recorder::type();

                        if (is_string($recorderType)) {
                            return $recorderType;
                        }

                        return $recorderType->value;
                    },
                    array_keys($recorders)
                ),
                array_map(
                    fn ($recorder) => $this->container->get($recorder),
                    array_keys($recorders)
                )
            );

            $middleware = array_map(
                fn ($middleware) => $this->container->get($middleware),
                array_keys($middlewares)
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
                solutionProviderRepository:  $this->container->get(SolutionProviderRepositoryContract::class),
                argumentReducers: $this->container->get(ArgumentReducers::class),
                collectStackFrameArguments: $collectStackFrameArguments,
                resource: $this->container->get(Resource::class),
                scope: $this->container->get(Scope::class),
                stacktraceMapper: $this->container->get(StacktraceMapper::class),
                overriddenGroupings: $this->config->overriddenGroupings,
            );
        });

        if ($collectStackFrameArguments && $forcePHPStackFrameArgumentsIniSetting) {
            (new PhpStackFrameArgumentsFixer())->enable();
        }
    }

    public function boot(): void
    {
        $this->container->get(Flare::class)->bootRecorders();
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
