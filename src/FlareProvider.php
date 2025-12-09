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
use Spatie\FlareClient\Memory\Memory;
use Spatie\FlareClient\Recorders\ErrorRecorder\ErrorRecorder;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Sampling\NeverSampler;
use Spatie\FlareClient\Sampling\Sampler;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\CollectsResolver;
use Spatie\FlareClient\Support\Container;
use Spatie\FlareClient\Support\Ids;
use Spatie\FlareClient\Support\Lifecycle;
use Spatie\FlareClient\Support\PhpStackFrameArgumentsFixer;
use Spatie\FlareClient\Support\Recorders;
use Spatie\FlareClient\Support\Redactor;
use Spatie\FlareClient\Support\SentReports;
use Spatie\FlareClient\Support\Telemetry;
use Spatie\FlareClient\Time\Time;

class FlareProvider
{
    public readonly FlareMode $mode;

    /**
     * @param Closure(Container|IlluminateContainer, class-string<Recorder>, array):void|null $registerRecorderAndMiddlewaresCallback
     * @param class-string<CollectsResolver>|null $collectsResolver
     * @param Closure():bool|null $isUsingSubtasksClosure
     * @param Closure(bool):bool|null $shouldMakeSamplingDecisionClosure
     * @param Closure(Span):bool|null $gracefulSpanEnderClosure
     */
    public function __construct(
        protected FlareConfig $config,
        protected Container|IlluminateContainer $container,
        protected ?string $collectsResolver = null,
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
            exporter: $this->container->get(Exporter::class),
            resource: $this->container->get(Resource::class),
            scope: $this->container->get(Scope::class),
        ));

        $this->container->singleton(Sampler::class, fn () => new $this->config->sampler(
            $this->config->samplerConfig
        ));

        $this->container->singleton(Exporter::class, fn () => new $this->config->exporter);

        $this->container->singleton(BackTracer::class, fn () => new BackTracer(
            $this->config->applicationPath
        ));

        $this->container->singleton(Time::class, fn () => new $this->config->time);
        $this->container->singleton(Ids::class, fn () => new $this->config->ids);
        $this->container->singleton(Memory::class, fn () => new $this->config->memory);

        $this->container->singleton(SentReports::class);

        $this->container->singleton(Redactor::class, fn () => new Redactor(
            censorClientIps: $this->config->censorClientIps,
            censorHeaders: $this->config->censorHeaders,
            censorBodyFields: $this->config->censorBodyFields,
        ));

        $this->container->singleton(UserAttributesProvider::class, $this->config->userAttributesProvider);
        $this->container->singleton(GitAttributesProvider::class, fn () => new GitAttributesProvider($this->config->applicationPath));

        /** @var CollectsResolver $collects */
        $collects = (new ($this->collectsResolver ?? CollectsResolver::class))->execute($this->config->collects);

        $this->container->singleton(ArgumentReducers::class, fn () => match (true) {
            $collects->collectStackFrameArguments === false => ArgumentReducers::create([]),
            is_array($collects->argumentReducers) => ArgumentReducers::create($collects->argumentReducers),
            default => $collects->argumentReducers,
        });

        $this->container->singleton(SolutionProviderRepositoryContract::class, function () use ($collects) {
            /** @var SolutionProviderRepositoryContract $repository */
            $repository = new $this->config->solutionsProviderRepository;

            $repository->registerSolutionProviders($collects->solutionProviders);

            return $repository;
        });

        $this->container->singleton(Resource::class, function () use ($collects) {
            $resource = new Resource(
                $this->config->applicationName,
                $this->config->applicationVersion,
                $this->config->applicationStage,
                Telemetry::getName(),
                Telemetry::getVersion(),
            );

            foreach ($collects->resourceModifiers as $resourceModifier) {
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

        foreach ($collects->recorders as $recorderClass => $config) {
            ($this->registerRecorderAndMiddlewaresCallback)($this->container, $recorderClass, $config);
        }

        foreach ($collects->middlewares as $middlewareClass => $config) {
            ($this->registerRecorderAndMiddlewaresCallback)($this->container, $middlewareClass, $config);
        }

        $this->container->singleton(Recorders::class, fn () => new Recorders(
            recorderDefinitions: $collects->recorders,
        ));

        $this->container->singleton(Lifecycle::class, fn () => new Lifecycle(
            api: $this->container->get(Api::class),
            time: $this->container->get(Time::class),
            memory: $this->container->get(Memory::class),
            logger: $this->container->get(Logger::class),
            tracer: $this->container->get(Tracer::class),
            recorders: $this->container->get(Recorders::class),
            sentReports: $this->container->get(SentReports::class),
            resource: $this->container->get(Resource::class),
            isUsingSubtasksClosure: $this->isUsingSubtasksClosure,
            shouldMakeSamplingDecisionClosure: $this->shouldMakeSamplingDecisionClosure,
        ));

        $this->container->singleton(Tracer::class, fn () => new Tracer(
            api: $this->container->get(Api::class),
            limits: $this->config->traceLimits,
            time: $this->container->get(Time::class),
            ids: $this->container->get(Ids::class),
            memory: $this->container->get(Memory::class),
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
            tracer: $this->container->get(Tracer::class),
            recorders: $this->container->get(Recorders::class),
            disabled: $this->config->log === false || $this->mode === FlareMode::Disabled,
        ));

        $this->container->singleton(ReportFactory::class, fn () => new ReportFactory(
            time: $this->container->get(Time::class),
            ids: $this->container->get(Ids::class),
            resource: $this->container->get(Resource::class),
            argumentReducers: $this->container->get(ArgumentReducers::class),
            collectStackTraceArguments: $collects->collectStackFrameArguments,
            overriddenGroupings: $this->config->overriddenGroupings,
            applicationPath: $this->config->applicationPath,
        ));

        $this->container->singleton(Reporter::class, function () use ($collects) {
            $middleware = array_map(
                fn ($middleware) => $this->container->get($middleware),
                array_keys($collects->middlewares)
            );

            $recorders = $this->container->get(Recorders::class);

            return new Reporter(
                api: $this->container->get(Api::class),
                disabled: $this->config->report === false || $this->mode === FlareMode::Disabled,
                tracer: $this->container->get(Tracer::class),
                lifecycle: $this->container->get(Lifecycle::class),
                sentReports: $this->container->get(SentReports::class),
                reportErrorLevels: $this->config->reportErrorLevels,
                filterExceptionsCallable: $this->config->filterExceptionsCallable,
                filterReportsCallable: $this->config->filterReportsCallable,
                solutionProviderRepository: $this->container->get(SolutionProviderRepositoryContract::class),
                reportFactory: $this->container->get(ReportFactory::class),
                middleware: $middleware,
                recorders: $recorders,
                addReportsToTraces: $collects->collectErrorsWithTraces
            );
        });

        $this->container->singleton(Flare::class, fn () => new Flare(
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
        ));

        if ($collects->collectStackFrameArguments && $collects->forcePHPStackFrameArgumentsIniSetting) {
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
