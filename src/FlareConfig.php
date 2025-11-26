<?php

namespace Spatie\FlareClient;

use Closure;
use Exception;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\Backtrace\Arguments\Reducers\ArgumentReducer;
use Spatie\Backtrace\Arguments\Reducers\ArrayArgumentReducer;
use Spatie\Backtrace\Arguments\Reducers\BaseTypeArgumentReducer;
use Spatie\Backtrace\Arguments\Reducers\ClosureArgumentReducer;
use Spatie\Backtrace\Arguments\Reducers\DateTimeArgumentReducer;
use Spatie\Backtrace\Arguments\Reducers\DateTimeZoneArgumentReducer;
use Spatie\Backtrace\Arguments\Reducers\EnumArgumentReducer;
use Spatie\Backtrace\Arguments\Reducers\StdClassArgumentReducer;
use Spatie\Backtrace\Arguments\Reducers\SymphonyRequestArgumentReducer;
use Spatie\ErrorSolutions\SolutionProviderRepository;
use Spatie\ErrorSolutions\SolutionProviders\BadMethodCallSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\MergeConflictSolutionProvider;
use Spatie\ErrorSolutions\SolutionProviders\UndefinedPropertySolutionProvider;
use Spatie\FlareClient\AttributesProviders\EmptyUserAttributesProvider;
use Spatie\FlareClient\AttributesProviders\UserAttributesProvider;
use Spatie\FlareClient\Contracts\FlareCollectType;
use Spatie\FlareClient\Contracts\Recorders\Recorder;
use Spatie\FlareClient\Enums\CollectType;
use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\Enums\MessageLevels;
use Spatie\FlareClient\Enums\OverriddenGrouping;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\Recorders\CacheRecorder\CacheRecorder;
use Spatie\FlareClient\Recorders\CommandRecorder\CommandRecorder;
use Spatie\FlareClient\Recorders\DumpRecorder\DumpRecorder;
use Spatie\FlareClient\Recorders\ErrorRecorder\ErrorRecorder;
use Spatie\FlareClient\Recorders\ExternalHttpRecorder\ExternalHttpRecorder;
use Spatie\FlareClient\Recorders\FilesystemRecorder\FilesystemRecorder;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Spatie\FlareClient\Recorders\LogRecorder\LogRecorder;
use Spatie\FlareClient\Recorders\QueryRecorder\QueryRecorder;
use Spatie\FlareClient\Recorders\RedisCommandRecorder\RedisCommandRecorder;
use Spatie\FlareClient\Recorders\TransactionRecorder\TransactionRecorder;
use Spatie\FlareClient\Recorders\ViewRecorder\ViewRecorder;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Sampling\AlwaysSampler;
use Spatie\FlareClient\Sampling\NeverSampler;
use Spatie\FlareClient\Sampling\RateSampler;
use Spatie\FlareClient\Sampling\Sampler;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Senders\CurlSender;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\CollectsResolver;
use Spatie\FlareClient\Support\Ids;
use Spatie\FlareClient\Support\StacktraceMapper;
use Spatie\FlareClient\Support\TraceLimits;
use Spatie\FlareClient\Time\SystemTime;
use Spatie\FlareClient\TraceExporters\OpenTelemetryJsonExporter;

class FlareConfig
{
    /**
     * @param null|Closure(Exception): bool $filterExceptionsCallable
     * @param null|Closure(Report): bool $filterReportsCallable
     * @param array<string, array{type: FlareCollectType, ignored: ?bool, options: array}> $collects
     * @param class-string<Sender> $sender
     * @param class-string<SolutionProviderRepository> $solutionsProviderRepository
     * @param Closure(Scope):void|null $configureScopeCallable
     * @param Closure(Resource):void|null $configureResourceCallable
     * @param Closure(Span):(void|Span)|null $configureSpansCallable
     * @param Closure(SpanEvent):(void|SpanEvent|null)|null $configureSpanEventsCallable
     * @param array<string> $censorHeaders
     * @param array<string> $censorBodyFields
     * @param class-string<UserAttributesProvider> $userAttributesProvider
     * @param array<class-string, OverriddenGrouping> $overriddenGroupings
     * @param class-string<CollectsResolver> $collectsResolver
     */
    public function __construct(
        public ?string $apiToken = null,
        public string $baseUrl = Api::BASE_URL,
        public bool $sendReportsImmediately = false,
        public array $collects = [],
        public ?int $reportErrorLevels = null,
        public ?Closure $filterExceptionsCallable = null,
        public ?Closure $filterReportsCallable = null,
        public ?string $applicationPath = null,
        public string $applicationName = 'PHP application',
        public ?string $applicationVersion = null,
        public ?string $applicationStage = null,
        public string $sender = CurlSender::class,
        public array $senderConfig = [],
        public string $solutionsProviderRepository = SolutionProviderRepository::class,
        public bool $trace = false,
        public string $sampler = RateSampler::class,
        public array $samplerConfig = [],
        public ?TraceLimits $traceLimits = null,
        public ?Closure $configureScopeCallable = null,
        public ?Closure $configureResourceCallable = null,
        public ?Closure $configureSpansCallable = null,
        public ?Closure $configureSpanEventsCallable = null,
        public bool $censorClientIps = false,
        public array $censorHeaders = [],
        public array $censorBodyFields = [],
        public string $userAttributesProvider = EmptyUserAttributesProvider::class,
        public string $traceExporter = OpenTelemetryJsonExporter::class,
        public string $stacktraceMapper = StacktraceMapper::class,
        public string $collectsResolver = CollectsResolver::class,
        public string $ids = Ids::class,
        public string $time = SystemTime::class,
        public array $overriddenGroupings = [],
        public bool $includeStackTraceWithMessages = false,
    ) {
    }

    public static function make(string $apiToken): static
    {
        return new static($apiToken);
    }

    public function censorClientIps(bool $censorClientIps = true): static
    {
        $this->censorClientIps = $censorClientIps;

        return $this;
    }

    public function censorHeaders(string ...$headers): static
    {
        array_push($this->censorHeaders, ...$headers);

        return $this;
    }

    public function censorBodyFields(string ...$fields): static
    {
        array_push($this->censorBodyFields, ...$fields);

        return $this;
    }

    public function useDefaults(): static
    {
        return $this
            ->collectErrorsWithTraces()
            ->collectDumps()
            ->collectCommands()
            ->collectRequests()
            ->collectCacheEvents()
            ->collectLogs()
            ->collectQueries()
            ->collectTransactions()
            ->collectExternalHttp()
            ->collectFilesystemOperations()
            ->collectGitInfo()
            ->collectViews()
            ->collectGlows()
            ->collectSolutions()
            ->collectErrorsWithTraces()
            ->collectStackFrameArguments()
            ->collectServerInfo()
            ->censorHeaders(
                'API-KEY',
                'Authorization',
                'Cookie',
                'Set-Cookie',
                'X-CSRF-TOKEN',
                'X-XSRF-TOKEN',
            )
            ->censorBodyFields(
                'password',
                'password_confirmation',
            );
    }

    public static function defaultSolutionProviders(): array
    {
        return [
            BadMethodCallSolutionProvider::class,
            MergeConflictSolutionProvider::class,
            UndefinedPropertySolutionProvider::class,
        ];
    }

    public static function defaultArgumentReducers(): array
    {
        return [
            BaseTypeArgumentReducer::class,
            ArrayArgumentReducer::class,
            StdClassArgumentReducer::class,
            EnumArgumentReducer::class,
            ClosureArgumentReducer::class,
            DateTimeArgumentReducer::class,
            DateTimeZoneArgumentReducer::class,
            SymphonyRequestArgumentReducer::class,
        ];
    }

    public function collectRequests(array $extra = []): static
    {
        return $this->addCollect(CollectType::Requests, $extra);
    }

    public function ignoreRequests(): static
    {
        return $this->ignoreCollect(CollectType::Requests);
    }

    public function collectCommands(
        bool $withTraces = CommandRecorder::DEFAULT_WITH_TRACES,
        bool $withErrors = CommandRecorder::DEFAULT_WITH_ERRORS,
        ?int $maxItemsWithErrors = CommandRecorder::DEFAULT_MAX_ITEMS_WITH_ERRORS,
        array $extra = [],
    ): static {
        return $this->addCollect(CollectType::Commands, [
            'with_traces' => $withTraces,
            'with_errors' => $withErrors,
            'max_items_with_errors' => $maxItemsWithErrors,
            ...$extra,
        ]);
    }

    public function ignoreCommands(): static
    {
        return $this->ignoreCollect(CollectType::Commands);
    }

    public function collectGitInfo(
        bool $useProcess = Resource::DEFAULT_GIT_USE_PROCESS,
        bool|FlareEntityType|array $entityTypes = Resource::DEFAULT_GIT_ENTITY_TYPES,
        array $extra = []
    ): static {
        return $this->addCollect(CollectType::GitInfo, [
            'use_process' => $useProcess,
            'entity_types' => $entityTypes,
            ...$extra,
        ]);
    }

    public function ignoreGitInfo(): static
    {
        return $this->ignoreCollect(CollectType::GitInfo);
    }

    public function collectCacheEvents(
        bool $withTraces = CacheRecorder::DEFAULT_WITH_TRACES,
        bool $withErrors = CacheRecorder::DEFAULT_WITH_ERRORS,
        ?int $maxItemsWithErrors = CacheRecorder::DEFAULT_MAX_ITEMS_WITH_ERRORS,
        array $operations = CacheRecorder::DEFAULT_OPERATIONS,
        array $extra = [],
    ): static {
        return $this->addCollect(CollectType::Cache, [
            'with_traces' => $withTraces,
            'with_errors' => $withErrors,
            'max_items_with_errors' => $maxItemsWithErrors,
            'operations' => $operations,
            ...$extra,
        ]);
    }

    public function ignoreCacheEvents(): static
    {
        return $this->ignoreCollect(CollectType::Cache);
    }

    public function collectGlows(
        bool $withTraces = GlowRecorder::DEFAULT_WITH_TRACES,
        bool $withErrors = GlowRecorder::DEFAULT_WITH_ERRORS,
        ?int $maxItemsWithErrors = GlowRecorder::DEFAULT_MAX_ITEMS_WITH_ERRORS,
    ): static {
        return $this->addCollect(CollectType::Glows, [
            'with_traces' => $withTraces,
            'with_errors' => $withErrors,
            'max_items_with_errors' => $maxItemsWithErrors,
        ]);
    }

    public function ignoreGlows(): static
    {
        return $this->ignoreCollect(CollectType::Glows);
    }

    public function collectLogs(
        bool $withTraces = LogRecorder::DEFAULT_WITH_TRACES,
        bool $withErrors = LogRecorder::DEFAULT_WITH_ERRORS,
        ?int $maxItemsWithErrors = LogRecorder::DEFAULT_MAX_ITEMS_WITH_ERRORS,
        MessageLevels $minimalLevel = MessageLevels::Debug,
    ): static {
        return $this->addCollect(CollectType::Logs, [
            'with_traces' => $withTraces,
            'with_errors' => $withErrors,
            'max_items_with_errors' => $maxItemsWithErrors,
            'minimal_level' => $minimalLevel,
        ]);
    }

    public function ignoreLogs(): static
    {
        return $this->ignoreCollect(CollectType::Logs);
    }

    public function collectSolutions(
        ?array $solutionProviders = null,
        array $extra = [],
    ): static {
        return $this->addCollect(CollectType::Solutions, [
            'solution_providers' => $solutionProviders ?? static::defaultSolutionProviders(),
            ...$extra,
        ]);
    }

    public function ignoreSolutions(): static
    {
        return $this->ignoreCollect(CollectType::Solutions);
    }

    public function collectDumps(
        bool $withTraces = DumpRecorder::DEFAULT_WITH_TRACES,
        bool $withErrors = DumpRecorder::DEFAULT_WITH_ERRORS,
        ?int $maxItemsWithErrors = DumpRecorder::DEFAULT_MAX_ITEMS_WITH_ERRORS,
        bool $findOrigin = DumpRecorder::DEFAULT_FIND_ORIGIN,
        array $extra = [],
    ): static {
        return $this->addCollect(CollectType::Dumps, [
            'with_traces' => $withTraces,
            'with_errors' => $withErrors,
            'max_items_with_errors' => $maxItemsWithErrors,
            'find_dump' => $findOrigin,
            ...$extra,
        ]);
    }

    public function ignoreDumps(): static
    {
        return $this->ignoreCollect(CollectType::Dumps);
    }

    public function collectErrorsWithTraces(
        bool $withTraces = ErrorRecorder::DEFAULT_WITH_TRACES,
    ): static {
        return $this->addCollect(CollectType::ErrorsWithTraces, [
            'with_traces' => $withTraces,
        ]);
    }

    public function ignoreErrorsWithTraces(): static
    {
        return $this->ignoreCollect(CollectType::ErrorsWithTraces);
    }

    public function collectQueries(
        bool $withTraces = QueryRecorder::DEFAULT_WITH_TRACES,
        bool $withErrors = QueryRecorder::DEFAULT_WITH_ERRORS,
        ?int $maxItemsWithErrors = QueryRecorder::DEFAULT_MAX_ITEMS_WITH_ERRORS,
        bool $includeBindings = QueryRecorder::DEFAULT_INCLUDE_BINDINGS,
        bool $findOrigin = QueryRecorder::DEFAULT_FIND_ORIGIN,
        ?int $findOriginThreshold = QueryRecorder::DEFAULT_FIND_ORIGIN_THRESHOLD,
        array $extra = [],
    ): static {
        return $this->addCollect(CollectType::Queries, [
            'with_traces' => $withTraces,
            'with_errors' => $withErrors,
            'max_items_with_errors' => $maxItemsWithErrors,
            'include_bindings' => $includeBindings,
            'find_origin' => $findOrigin,
            'find_origin_threshold' => $findOriginThreshold,
            ...$extra,
        ]);
    }

    public function ignoreQueries(): static
    {
        return $this->ignoreCollect(CollectType::Queries);
    }

    public function collectTransactions(
        bool $withTraces = TransactionRecorder::DEFAULT_WITH_TRACES,
        bool $withErrors = TransactionRecorder::DEFAULT_WITH_ERRORS,
        ?int $maxItemsWithErrors = TransactionRecorder::DEFAULT_MAX_ITEMS_WITH_ERRORS,
        array $extra = [],
    ): static {
        return $this->addCollect(CollectType::Transactions, [
            'with_traces' => $withTraces,
            'with_errors' => $withErrors,
            'max_items_with_errors' => $maxItemsWithErrors,
            ...$extra,
        ]);
    }

    public function ignoreTransactions(): static
    {
        return $this->ignoreCollect(CollectType::Transactions);
    }

    public function collectExternalHttp(
        bool $withTraces = ExternalHttpRecorder::DEFAULT_WITH_TRACES,
        bool $withErrors = ExternalHttpRecorder::DEFAULT_WITH_ERRORS,
        ?int $maxItemsWithErrors = ExternalHttpRecorder::DEFAULT_MAX_ITEMS_WITH_ERRORS,
        array $extra = [],
    ): static {
        return $this->addCollect(CollectType::ExternalHttp, [
            'with_traces' => $withTraces,
            'with_errors' => $withErrors,
            'max_items_with_errors' => $maxItemsWithErrors,
            ...$extra,
        ]);
    }

    public function ignoreExternalHttp(): static
    {
        return $this->ignoreCollect(CollectType::ExternalHttp);
    }

    public function collectFilesystemOperations(
        bool $withTraces = FilesystemRecorder::DEFAULT_WITH_TRACES,
        bool $withErrors = FilesystemRecorder::DEFAULT_WITH_ERRORS,
        ?int $maxItemsWithErrors = FilesystemRecorder::DEFAULT_MAX_ITEMS_WITH_ERRORS,
        array $extra = [],
    ): static {
        return $this->addCollect(CollectType::Filesystem, [
            'with_traces' => $withTraces,
            'with_errors' => $withErrors,
            'max_items_with_errors' => $maxItemsWithErrors,
            ...$extra,
        ]);
    }

    public function ignoreFilesystemOperations(): static
    {
        return $this->ignoreCollect(CollectType::Filesystem);
    }

    public function collectRedisCommands(
        bool $withTraces = true,
        bool $withErrors = true,
        ?int $maxItemsWithErrors = RedisCommandRecorder::DEFAULT_MAX_ITEMS_WITH_ERRORS,
        array $extra = [],
    ): static {
        return $this->addCollect(CollectType::RedisCommands, [
            'with_traces' => $withTraces,
            'with_errors' => $withErrors,
            'max_items_with_errors' => $maxItemsWithErrors,
            ...$extra,
        ]);
    }

    public function ignoreRedisCommands(): static
    {
        return $this->ignoreCollect(CollectType::RedisCommands);
    }

    public function collectViews(
        bool $withTraces = ViewRecorder::DEFAULT_WITH_TRACES,
        bool $withErrors = ViewRecorder::DEFAULT_WITH_ERRORS,
        array $extra = [],
    ): static {
        return $this->addCollect(CollectType::Views, [
            'with_traces' => $withTraces,
            'with_errors' => $withErrors,
            ...$extra,
        ]);
    }

    public function ignoreViews(): static
    {
        return $this->ignoreCollect(CollectType::Views);
    }

    /**
     * @param bool|array<int, FlareEntityType>|FlareEntityType $host
     * @param bool|array<int, FlareEntityType>|FlareEntityType $os
     * @param bool|array<int, FlareEntityType>|FlareEntityType $php
     * @param bool|array<int, FlareEntityType>|FlareEntityType $composer
     * @param bool|array<int, FlareEntityType>|FlareEntityType $composerPackages
     */
    public function collectServerInfo(
        bool|array|FlareEntityType $host = Resource::DEFAULT_HOST_ENTITY_TYPES,
        bool|array|FlareEntityType $os = Resource::DEFAULT_OS_ENTITY_TYPES,
        bool|array|FlareEntityType $php = Resource::DEFAULT_PHP_ENTITY_TYPES,
        bool|array|FlareEntityType $composer = false,
        bool|array|FlareEntityType $composerPackages = Resource::DEFAULT_COMPOSER_PACKAGES_ENTITY_TYPES,
        array $extra = [],
    ): static {
        return $this->addCollect(CollectType::ServerInfo, [
            'host' => $host,
            'os' => $os,
            'php' => $php,
            'composer' => $composer,
            'composer_packages' => $composerPackages,
            ...$extra,
        ]);
    }

    public function ignoreServerInfo(): static
    {
        return $this->ignoreCollect(CollectType::ServerInfo);
    }

    /** @param array<class-string<Recorder>, array<string, mixed>> $recorders */
    public function collectRecorders(
        array $recorders = [],
    ): static {
        return $this->addCollect(CollectType::Recorders, [
            'recorders' => $recorders,
        ]);
    }

    /** @param array<class-string<FlareMiddleware>, array<string, mixed>> $middleware */
    public function collectFlareMiddleware(
        array $middleware = [],
    ): static {
        return $this->addCollect(CollectType::FlareMiddleware, [
            'flare_middleware' => $middleware,
        ]);
    }

    /**
     * @param string|Closure(): string $version
     */
    public function applicationVersion(string|Closure $version): static
    {
        $this->applicationVersion = is_callable($version) ? $version() : $version;

        return $this;
    }

    /**
     * @param string|Closure(): string $name
     */
    public function applicationName(string|Closure $name): static
    {
        $this->applicationName = is_callable($name) ? $name() : $name;

        return $this;
    }

    /**
     * @param string|Closure(): string $stage
     */
    public function applicationStage(string|Closure $stage): static
    {
        $this->applicationStage = is_callable($stage) ? $stage() : $stage;

        return $this;
    }

    public function applicationPath(string $applicationPath): static
    {
        $this->applicationPath = $applicationPath;

        return $this;
    }

    public function collectStackFrameArguments(
        string|ArgumentReducers|ArgumentReducer|array|null $argumentReducers = null,
        bool $forcePHPIniSetting = true
    ): static {
        $argumentReducers = match (true) {
            $argumentReducers === null => static::defaultArgumentReducers(),
            $argumentReducers instanceof ArgumentReducers, is_array($argumentReducers) => $argumentReducers,
            $argumentReducers instanceof ArgumentReducer, is_string($argumentReducers) => [$argumentReducers],
        };

        return $this->addCollect(CollectType::StackFrameArguments, [
            'argument_reducers' => $argumentReducers,
            'force_php_ini_setting' => $forcePHPIniSetting,
        ]);
    }

    public function ignoreStackFrameArguments(): static
    {
        return $this->ignoreCollect(CollectType::StackFrameArguments);
    }

    /**
     * @param Closure(Exception): bool $filterExceptionsCallable
     */
    public function filterExceptionsUsing(Closure $filterExceptionsCallable): static
    {
        $this->filterExceptionsCallable = $filterExceptionsCallable;

        return $this;
    }

    /**
     * @param Closure(Report): bool $filterReportsCallable
     */
    public function filterReportsUsing(Closure $filterReportsCallable): static
    {
        $this->filterReportsCallable = $filterReportsCallable;

        return $this;
    }

    public function reportErrorLevels(int $reportErrorLevels): static
    {
        $this->reportErrorLevels = $reportErrorLevels;

        return $this;
    }

    public function trace(
        bool $trace = true,
        int $maxSpans = 512,
        int $maxAttributesPerSpan = 128,
        int $maxSpanEventsPerSpan = 128,
        int $maxAttributesPerSpanEvent = 128,
    ): static {
        $this->trace = $trace;
        $this->traceLimits = new TraceLimits(
            $maxSpans,
            $maxAttributesPerSpan,
            $maxSpanEventsPerSpan,
            $maxAttributesPerSpanEvent,
        );

        return $this;
    }

    /**
     * @param Closure(Scope):void $configureScopeCallable
     */
    public function configureScope(Closure $configureScopeCallable): static
    {
        $this->configureScopeCallable = $configureScopeCallable;

        return $this;
    }

    /**
     * @param Closure(Resource):void $configureResourceCallable
     */
    public function configureResource(Closure $configureResourceCallable): static
    {
        $this->configureResourceCallable = $configureResourceCallable;

        return $this;
    }

    /**
     * @param Closure(Span):(void|Span) $configureSpansCallable
     */
    public function configureSpans(Closure $configureSpansCallable): static
    {
        $this->configureSpansCallable = $configureSpansCallable;

        return $this;
    }

    /**
     * @param Closure(SpanEvent):(void|null|SpanEvent) $configureSpanEventsCallable
     */
    public function configureSpanEvents(Closure $configureSpanEventsCallable): static
    {
        $this->configureSpanEventsCallable = $configureSpanEventsCallable;

        return $this;
    }

    public function alwaysSampleTraces(): static
    {
        $this->sampler = AlwaysSampler::class;

        return $this;
    }

    public function neverSampleTraces(): static
    {
        $this->sampler = NeverSampler::class;

        return $this;
    }

    public function sampleRate(float $rate): static
    {
        $this->sampler = RateSampler::class;
        $this->samplerConfig = ['rate' => $rate];

        return $this;
    }

    /**
     * @param class-string<Sampler> $sampler
     */
    public function sampler(string $sampler, array $config = []): static
    {
        $this->sampler = $sampler;
        $this->samplerConfig = $config;

        return $this;
    }

    public function sendReportsImmediately(bool $sendReportsImmediately = true): static
    {
        $this->sendReportsImmediately = $sendReportsImmediately;

        return $this;
    }

    /**
     * @param class-string<Sender> $senderClass
     */
    public function sendUsing(
        string $senderClass,
        array $config = []
    ): static {
        $this->sender = $senderClass;
        $this->senderConfig = $config;

        return $this;
    }

    /**
     * @param class-string $class
     */
    public function overrideGrouping(
        string $class,
        OverriddenGrouping $override
    ): static {
        $this->overriddenGroupings[$class] = $override;

        return $this;
    }

    public function traceLimits(TraceLimits $traceLimits): static
    {
        $this->traceLimits = $traceLimits;

        return $this;
    }

    /**
     * @param class-string<UserAttributesProvider> $userAttributesProvider
     */
    public function userAttributesProvider(
        string $userAttributesProvider
    ): static {
        $this->userAttributesProvider = $userAttributesProvider;

        return $this;
    }

    public function includeStackTraceWithMessages(
        bool $includeStackTraceWithMessages = true
    ): static {
        $this->includeStackTraceWithMessages = $includeStackTraceWithMessages;

        return $this;
    }

    protected function addCollect(
        FlareCollectType $type,
        array $options = [],
    ): static {
        $this->collects[$type->value]['type'] = $type;
        $this->collects[$type->value]['options'] = $options;

        return $this;
    }

    protected function ignoreCollect(FlareCollectType $type): static
    {
        $this->collects[$type->value]['type'] = $type;
        $this->collects[$type->value]['ignored'] = true;

        return $this;
    }
}
