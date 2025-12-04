<?php

namespace Spatie\FlareClient;

use Closure;
use Illuminate\Contracts\Container\Container as IlluminateContainer;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\ErrorSolutions\Contracts\SolutionProviderRepository as SolutionProviderRepositoryContract;
use Spatie\FlareClient\AttributesProviders\GitAttributesProvider;
use Spatie\FlareClient\AttributesProviders\UserAttributesProvider;
use Spatie\FlareClient\Contracts\Recorders\Recorder;
use Spatie\FlareClient\Enums\FlareMode;
use Spatie\FlareClient\Exporters\Exporter;
use Spatie\FlareClient\FlareMiddleware\AddRecordedEntries;
use Spatie\FlareClient\Recorders\ErrorRecorder\ErrorRecorder;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Sampling\NeverSampler;
use Spatie\FlareClient\Sampling\Sampler;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\Container;
use Spatie\FlareClient\Support\Ids;
use Spatie\FlareClient\Support\Lifecycle;
use Spatie\FlareClient\Support\PhpStackFrameArgumentsFixer;
use Spatie\FlareClient\Support\Recorders;
use Spatie\FlareClient\Support\Redactor;
use Spatie\FlareClient\Support\SentReports;
use Spatie\FlareClient\Support\StacktraceMapper;
use Spatie\FlareClient\Support\Telemetry;
use Spatie\FlareClient\Support\TraceLimits;
use Spatie\FlareClient\Time\Time;

class FlareProvider
{
    public readonly FlareMode $mode;

    /**
     * @param Closure(Container|IlluminateContainer, class-string<Recorder>, array):void|null $registerRecorderAndMiddlewaresCallback
     * @param Closure():bool|null $usesSubtasksClosure
     * @param Closure(bool):bool|null $shouldMakeSamplingDecisionClosure
     * @param Closure(Span):bool|null $gracefulSpanEnderClosure
     */
    public function __construct(
        protected FlareConfig $config,
        protected Container|IlluminateContainer $container,
        protected ?Closure $registerRecorderAndMiddlewaresCallback = null,
        protected ?Closure $isUsingSubtasksClosure = null,
        protected ?Closure $shouldMakeSamplingDecisionClosure = null,
        protected ?Closure $gracefulSpanEnderClosure = null,
    ) {
        $this->registerRecorderAndMiddlewaresCallback ??= $this->defaultRegisterRecordersAndMiddlewaresCallback();
        $this->mode = match (true) {
            empty($this->config->apiToken) && $this->config->applicationStage === 'local' => FlareMode::Ignition,
            empty($this->config->apiToken) => FlareMode::Disabled,
            default => FlareMode::Enabled,
        };
    }

    public function register(): void
    {
        $this->container ??= Container::instance();

        $this->container->singleton(Sender::class, fn () => new $this->config->sender(
            $this->config->senderConfig
        ));

        $this->container->singleton(Api::class, fn () => new ($this->config->api)(
            apiToken: $this->config->apiToken ?? 'No Api Token provided',
            baseUrl: $this->config->baseUrl,
            sender: $this->container->get(Sender::class),
        ));

        $this->container->singleton(Sampler::class, fn () => new $this->config->sampler(
            $this->config->samplerConfig
        ));

        $this->container->singleton(Exporter::class, fn () => new $this->config->traceExporter);

        $this->container->singleton(StacktraceMapper::class, fn () => new $this->config->stacktraceMapper);

        $this->container->singleton(BackTracer::class, fn () => new BackTracer(
            $this->config->applicationPath
        ));

        $this->container->singleton(Time::class, fn () => new $this->config->time);
        $this->container->singleton(Ids::class, fn () => new $this->config->ids);

        $this->container->singleton(SentReports::class);

        $this->container->singleton(ErrorRecorder::class, fn () => new ErrorRecorder(
            $this->container->get(Tracer::class),
            $this->container->get(BackTracer::class),
            [],
        ));

        $this->container->singleton(Redactor::class, fn () => new Redactor(
            censorClientIps: $this->config->censorClientIps,
            censorHeaders: $this->config->censorHeaders,
            censorBodyFields: $this->config->censorBodyFields,
        ));

        $this->container->singleton(UserAttributesProvider::class, $this->config->userAttributesProvider);
        $this->container->singleton(GitAttributesProvider::class, fn () => new GitAttributesProvider($this->config->applicationPath));

        [
            'middlewares' => $middlewares,
            'recorders' => $recorders,
            'solutionProviders' => $solutionProviders,
            'resourceModifiers' => $resourceModifiers,
            'collectStackFrameArguments' => $collectStackFrameArguments,
            'argumentReducers' => $argumentReducers,
            'forcePHPStackFrameArgumentsIniSetting' => $forcePHPStackFrameArgumentsIniSetting,
            'collectErrorsWithTraces' => $collectErrorsWithTraces,
        ] = (new $this->config->collectsResolver())->execute($this->config->collects);

        if ($this->mode === FlareMode::Disabled) {
            $middlewares = [];
            $recorders = [];
            $solutionProviders = [];
            $resourceModifiers = [];
            $argumentReducers = [];
            $forcePHPStackFrameArgumentsIniSetting = false;
            $collectErrorsWithTraces = false;
        }

        $this->container->singleton(ArgumentReducers::class, fn () => match (true) {
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
                Telemetry::getName(),
                Telemetry::getVersion(),
            );

            foreach ($resourceModifiers as $resourceModifier) {
                $resource = $resourceModifier($resource, $this->container);
            }

            if ($this->config->configureResourceCallable) {
                ($this->config->configureResourceCallable)($resource);
            }

            return $resource;
        });

        $this->container->singleton(Scope::class, function () {
            $scope = new Scope(
                Telemetry::getName(),
                Telemetry::getVersion(),
            );

            if ($this->config->configureScopeCallable) {
                ($this->config->configureScopeCallable)($scope);
            }

            return $scope;
        });

        foreach ($recorders as $recorderClass => $config) {
            ($this->registerRecorderAndMiddlewaresCallback)($this->container, $recorderClass, $config);
        }

        foreach ($middlewares as $middlewareClass => $config) {
            ($this->registerRecorderAndMiddlewaresCallback)($this->container, $middlewareClass, $config);
        }

        $this->container->singleton(Recorders::class, fn () => new Recorders(
            recorderDefinitions: $recorders,
        ));

        $this->container->singleton(Lifecycle::class, fn () => new Lifecycle(
            api: $this->container->get(Api::class),
            time: $this->container->get(Time::class),
            logger: $this->container->get(Logger::class),
            tracer: $this->container->get(Tracer::class),
            recorders: $this->container->get(Recorders::class),
            sentReports: $this->container->get(SentReports::class),
            isUsingSubtasksClosure: $this->isUsingSubtasksClosure,
            shouldMakeSamplingDecisionClosure: $this->shouldMakeSamplingDecisionClosure,
        ));

        $this->container->singleton(Tracer::class, fn () => new Tracer(
            api: $this->container->get(Api::class),
            exporter: $this->container->get(Exporter::class),
            limits: $this->config->traceLimits ?? new TraceLimits(),
            time: $this->container->get(Time::class),
            ids: $this->container->get(Ids::class),
            resource: $this->container->get(Resource::class),
            scope: $this->container->get(Scope::class),
            recorders: $this->container->get(Recorders::class),
            sampler: $this->config->trace
                ? $this->container->get(Sampler::class)
                : new NeverSampler(),
            configureSpansCallable: $this->config->configureSpansCallable,
            configureSpanEventsCallable: $this->config->configureSpanEventsCallable,
            sampling: false,
            disabled: $this->config->trace === false || $this->mode === FlareMode::Disabled,
            gracefulSpanEnderClosure: $this->gracefulSpanEnderClosure
        ));

        $this->container->singleton(Logger::class, fn () => new Logger(
            api: $this->container->get(Api::class),
            time: $this->container->get(Time::class),
            exporter: $this->container->get(Exporter::class),
            tracer: $this->container->get(Tracer::class),
            resource: $this->container->get(Resource::class),
            scope: $this->container->get(Scope::class),
            disabled: $this->config->log === false || $this->mode === FlareMode::Disabled,
        ));

        $this->container->singleton(ReportFactory::class, fn () => new ReportFactory(
            stacktraceMapper: $this->container->get(StacktraceMapper::class),
            time: $this->container->get(Time::class),
            ids: $this->container->get(Ids::class),
            resource: $this->container->get(Resource::class),
            argumentReducers: $this->container->get(ArgumentReducers::class),
            collectStackTraceArguments: $collectStackFrameArguments,
            overriddenGroupings: $this->config->overriddenGroupings,
            applicationPath: $this->config->applicationPath,
        ));

        $this->container->singleton(Reporter::class, function () use ($middlewares, $collectErrorsWithTraces) {
            $middleware = array_map(
                fn ($middleware) => $this->container->get($middleware),
                array_keys($middlewares)
            );

            $recorders = $this->container->get(Recorders::class);

            return new Reporter(
                api: $this->container->get(Api::class),
                disabled: $this->config->report === false || $this->mode === FlareMode::Disabled,
                tracer: $this->container->get(Tracer::class),
                throwableRecorder: $collectErrorsWithTraces ? $this->container->get(ErrorRecorder::class) : null,
                sentReports: $this->container->get(SentReports::class),
                reportErrorLevels: $this->config->reportErrorLevels,
                filterExceptionsCallable: $this->config->filterExceptionsCallable,
                filterReportsCallable: $this->config->filterReportsCallable,
                solutionProviderRepository: $this->container->get(SolutionProviderRepositoryContract::class),
                reportFactory: $this->container->get(ReportFactory::class),
                middleware: $middleware,
                recorders: $recorders,
            );
        });

        $this->container->singleton(Flare::class, function () use ($collectErrorsWithTraces, $collectStackFrameArguments, $middlewares, $recorders) {
            return new Flare(
                lifecycle: $this->container->get(Lifecycle::class),
                tracer: $this->container->get(Tracer::class),
                logger: $this->container->get(Logger::class),
                reporter: $this->container->get(Reporter::class),
                backTracer: $this->container->get(BackTracer::class),
                ids: $this->container->get(Ids::class),
                time: $this->container->get(Time::class),
                sentReports: $this->container->get(SentReports::class),
                resource: $this->container->get(Resource::class),
                scope: $this->container->get(Scope::class),
                recorders: $this->container->get(Recorders::class),
            );
        });

        if ($collectStackFrameArguments && $forcePHPStackFrameArgumentsIniSetting) {
            (new PhpStackFrameArgumentsFixer())->enable();
        }
    }

    public function boot(): void
    {
        if ($this->mode === FlareMode::Disabled) {
            return;
        }

        $this->container->get(Recorders::class)->boot($this->container);
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
