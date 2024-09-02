<?php

namespace Spatie\FlareClient;

use Closure;
use Exception;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\Backtrace\Arguments\Reducers\ArgumentReducer;
use Spatie\ErrorSolutions\Contracts\HasSolutionsForThrowable;
use Spatie\ErrorSolutions\SolutionProviderRepository;
use Spatie\FlareClient\Contracts\Recorders\Recorder;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\FlareMiddleware\AddConsoleInformation;
use Spatie\FlareClient\FlareMiddleware\AddGitInformation;
use Spatie\FlareClient\FlareMiddleware\AddRequestInformation;
use Spatie\FlareClient\FlareMiddleware\AddSolutions;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\Recorders\CacheRecorder\CacheRecorder;
use Spatie\FlareClient\Recorders\CommandRecorder\CommandRecorder;
use Spatie\FlareClient\Recorders\DumpRecorder\DumpRecorder;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Spatie\FlareClient\Recorders\LogRecorder\LogRecorder;
use Spatie\FlareClient\Recorders\QueryRecorder\QueryRecorder;
use Spatie\FlareClient\Recorders\TransactionRecorder\TransactionRecorder;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Sampling\AlwaysSampler;
use Spatie\FlareClient\Sampling\RateSampler;
use Spatie\FlareClient\Sampling\Sampler;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Senders\CurlSender;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Support\TraceLimits;

class FlareConfig
{
    /**
     * @param null|Closure(Exception): bool $filterExceptionsCallable
     * @param null|Closure(Report): bool $filterReportsCallable
     * @param array<class-string<FlareMiddleware>, array> $middleware
     * @param array<class-string<Recorder>, array> $recorders
     * @param array<class-string<ArgumentReducer>|ArgumentReducer>|ArgumentReducers|null $argumentReducers
     * @param class-string<Sender> $sender
     * @param class-string<SolutionProviderRepository> $solutionsProviderRepository
     * @param array<class-string<HasSolutionsForThrowable>> $solutionsProviders
     * @param Closure(Scope):void|null $configureScopeCallable
     * @param Closure(Resource):void|null $configureResourceCallable
     * @param array<string> $censorHeaders
     * @param array<string> $censorBodyFields
     */
    public function __construct(
        public ?string $apiToken = null,
        public string $baseUrl = 'https://reporting.flareapp.io/api',
        public int $timeout = 10,
        public bool $sendReportsImmediately = false,
        public array $middleware = [],
        public array $recorders = [],
        public ?int $reportErrorLevels = null,
        public ?Closure $filterExceptionsCallable = null,
        public ?Closure $filterReportsCallable = null,
        public ?string $applicationPath = null,
        public ?string $applicationName = 'My Application',
        public ?string $applicationVersion = null,
        public ?string $applicationStage = null,
        public null|array|ArgumentReducers $argumentReducers = [],
        public bool $withStackFrameArguments = true,
        public bool $forcePHPStackFrameArgumentsIniSetting = true,
        public string $sender = CurlSender::class,
        public array $senderConfig = [],
        public string $solutionsProviderRepository = SolutionProviderRepository::class,
        public array $solutionsProviders = [],
        public bool $trace = true,
        public string $sampler = RateSampler::class,
        public array $samplerConfig = [],
        public ?TraceLimits $traceLimits = null,
        public bool $traceThrowables = true,
        public ?Closure $configureScopeCallable = null,
        public ?Closure $configureResourceCallable = null,
        public bool $censorClientIps = false,
        public array $censorHeaders = [],
        public array $censorBodyFields = [],
    ) {
    }

    public static function make(string $apiToken): static
    {
        return new static($apiToken);
    }

    public function censorClientIps(bool $censorClientIps = true): self
    {
        $this->censorClientIps = $censorClientIps;
    }

    public function censorHeaders(string ...$headers): self
    {
        array_push($this->censorHeaders, ...$headers);

        return $this;
    }

    public function censorBodyFields(string ...$fields): self
    {
        array_push($this->censorBodyFields, ...$fields);

        return $this;
    }

    public function useDefaults(): static
    {
        return $this
            ->addDumps()
            ->addCommands()
            ->addRequestInfo()
            ->addConsoleInfo()
            ->addGitInfo()
            ->addGlows()
            ->addSolutions()
            ->addThrowables()
            ->addStackFrameArguments()
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

    public function addRequestInfo(
        string $middleware = AddRequestInformation::class,
    ): self {
        $this->middleware[$middleware] = [];

        return $this;
    }

    public function addConsoleInfo(
        string $middleware = AddConsoleInformation::class,
    ): self {
        $this->middleware[$middleware] = [];

        return $this;
    }

    public function addGitInfo(
        string $middleware = AddGitInformation::class,
    ): static {
        $this->middleware[$middleware] = [];

        return $this;
    }

    public function addCacheEvents(
        bool $trace = true,
        bool $report = true,
        ?int $maxReported = 100,
        array $events = [SpanEventType::CacheHit, SpanEventType::CacheMiss, SpanEventType::CacheKeyWritten, SpanEventType::CacheKeyForgotten],
        string $recorder = CacheRecorder::class,
    ): static {
        $this->recorders[$recorder] = [
            'trace' => $trace,
            'report' => $report,
            'max_reported' => $maxReported,
            'events' => $events,
        ];

        return $this;
    }

    public function addGlows(
        bool $trace = true,
        bool $report = true,
        ?int $maxReported = 10,
        string $recorder = GlowRecorder::class,
    ): static {
        $this->recorders[$recorder] = [
            'trace' => $trace,
            'report' => $report,
            'max_reported' => $maxReported,
        ];

        return $this;
    }

    public function addLogs(
        bool $trace = true,
        bool $report = true,
        ?int $maxReported = 10,
        string $recorder = LogRecorder::class,
    ): static {
        $this->recorders[$recorder] = [
            'trace' => $trace,
            'report' => $report,
            'max_reported' => $maxReported,
        ];

        return $this;
    }

    public function addCommands(
        bool $trace = true,
        bool $report = true,
        ?int $maxReported = 10,
        string $recorder = CommandRecorder::class,
    ): static {
        $this->recorders[$recorder] = [
            'trace' => $trace,
            'report' => $report,
            'max_reported' => $maxReported,
        ];

        return $this;
    }

    public function addSolutions(
        string $middleware = AddSolutions::class,
    ): static {
        $this->middleware[$middleware] = [];

        return $this;
    }

    public function addDumps(
        bool $trace = false,
        bool $report = true,
        ?int $maxReported = 25,
        bool $findOrigin = false,
        string $recorder = DumpRecorder::class
    ): static {
        $this->recorders[$recorder] = [
            'trace' => $trace,
            'report' => $report,
            'max_reported' => $maxReported,
            'find_dump' => $findOrigin,
        ];

        return $this;
    }

    public function addThrowables(
        bool $trace = true,
    ): static {
        $this->traceThrowables = $trace;

        return $this;
    }

    public function addQueries(
        bool $trace = true,
        bool $report = true,
        ?int $maxReported = 100,
        bool $includeBindings = true,
        bool $findOrigin = true,
        ?int $findOriginThreshold = 300_000,
        string $recorder = QueryRecorder::class,
    ): static {
        $this->recorders[$recorder] = [
            'trace' => $trace,
            'report' => $report,
            'max_reported' => $maxReported,
            'include_bindings' => $includeBindings,
            'find_origin' => $findOrigin,
            'find_origin_threshold' => $findOriginThreshold,
        ];

        return $this;
    }

    public function addTransactions(
        bool $trace = true,
        bool $report = true,
        ?int $maxReported = 100,
        string $recorder = TransactionRecorder::class
    ): static {
        $this->recorders[$recorder] = [
            'trace' => $trace,
            'report' => $report,
            'max_reported' => $maxReported,
        ];

        return $this;
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

    public function addStackFrameArguments(
        bool $withStackFrameArguments = true,
        string|ArgumentReducers|ArgumentReducer|array|null $argumentReducers = null,
        bool $forcePHPIniSetting = true
    ): static {
        $this->withStackFrameArguments = $withStackFrameArguments;
        $this->forcePHPStackFrameArgumentsIniSetting = $forcePHPIniSetting;

        $argumentReducers = match (true) {
            $argumentReducers === null => ArgumentReducers::default(),
            $argumentReducers instanceof ArgumentReducers, is_array($argumentReducers) => $argumentReducers,
            $argumentReducers instanceof ArgumentReducer, is_string($argumentReducers) => [$argumentReducers],
        };

        $this->argumentReducers = $argumentReducers;

        return $this;
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

    /**
     * @param array<class-string<HasSolutionsForThrowable>> ...$solutionProvider
     */
    public function solutionProvider(string ...$solutionProvider): static
    {
        array_push($this->solutionsProviders, ...$solutionProvider);

        return $this;
    }

    public function trace(
        bool $trace = true,
        int $maxSpans = 512,
        int $maxAttributesPerSpan = 128,
        int $maxSpanEventsPerSpan = 128,
        int $maxAttributesPerSpanEvent = 128,
        ?Closure $configureScope = null,
        ?Closure $configureResource = null,
    ): static {
        $this->trace = $trace;
        $this->traceLimits = new TraceLimits(
            $maxSpans,
            $maxAttributesPerSpan,
            $maxSpanEventsPerSpan,
            $maxAttributesPerSpanEvent,
        );

        $this->configureScopeCallable = $configureScope;
        $this->configureResourceCallable = $configureResource;

        return $this;
    }

    public function alwaysSampleTraces(): static
    {
        $this->sampler = AlwaysSampler::class;

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
     * @param class-string<FlareMiddleware> ...$middlewareClasses
     */
    public function removeMiddleware(string ...$middlewareClasses): self
    {
        foreach ($middlewareClasses as $middlewareClass) {
            unset($this->middleware[$middlewareClass]);
        }

        return $this;
    }

    public function removeAllMiddleware(): self
    {
        $this->middleware = [];

        return $this;
    }

    /**
     * @param class-string<Recorder> ...$recorderClasses
     */
    public function removeRecorder(string ...$recorderClasses): self
    {
        foreach ($recorderClasses as $recorderClass) {
            unset($this->recorders[$recorderClass]);
        }

        return $this;
    }

    public function removeAllRecorders(): self
    {
        $this->recorders = [];

        return $this;
    }

    /**
     * @param class-string<Sender> $senderClass
     */
    public function sendUsing(
        string $senderClass,
        array $config = []
    ): self {
        $this->sender = $senderClass;
        $this->senderConfig = $config;

        return $this;
    }
}
