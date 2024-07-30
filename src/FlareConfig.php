<?php

namespace Spatie\FlareClient;

use Closure;
use Exception;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\Backtrace\Arguments\Reducers\ArgumentReducer;
use Spatie\ErrorSolutions\Contracts\HasSolutionsForThrowable;
use Spatie\ErrorSolutions\SolutionProviderRepository;
use Spatie\FlareClient\Contracts\Recorder;
use Spatie\FlareClient\FlareMiddleware\AddConsoleInformation;
use Spatie\FlareClient\FlareMiddleware\AddGitInformation;
use Spatie\FlareClient\FlareMiddleware\AddRequestInformation;
use Spatie\FlareClient\FlareMiddleware\AddSolutions;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\Recorders\DumpRecorder\DumpRecorder;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Spatie\FlareClient\Sampling\AlwaysSampler;
use Spatie\FlareClient\Sampling\RateSampler;
use Spatie\FlareClient\Senders\CurlSender;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Support\TraceLimits;

class FlareConfig
{

    /**
     * @param array<class-string<FlareMiddleware>, array> $middleware
     * @param array<class-string<Recorder>, array> $recorders
     * @param array<class-string<ArgumentReducer>|ArgumentReducer>|ArgumentReducers|null $argumentReducers
     * @param class-string<Sender> $sender
     * @param class-string<SolutionProviderRepository> $solutionsProviderRepository
     * @param array<class-string<HasSolutionsForThrowable>> $solutionsProviders
     */
    public function __construct(
        public ?string $apiToken = null,
        public string $baseUrl = 'https://reporting.flareapp.io/api',
        public int $timeout = 10,
        public bool $sendReportsImmediately = false,
        public array $middleware = [],
        public array $recorders = [],
        public ?string $applicationPath = null,
        public ?int $reportErrorLevels = null,
        public ?Closure $filterExceptionsCallable = null,
        public ?Closure $filterReportsCallable = null,
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
    ) {
    }

    public static function make(string $apiToken): static
    {
        return new static($apiToken);
    }

    public static function makeDefault(string $apiToken): static
    {
        return (new static($apiToken))
            ->addDumps()
            ->addRequestInfo()
            ->addConsoleInfo()
            ->addGitInfo()
            ->addGlows()
            ->addSolutions()
            ->addStackFrameArguments()
            ->setArgumentReducers(ArgumentReducers::default());
    }

    public function addRequestInfo(
        array $censorBodyFields = ['password', 'password_confirmation'],
        array $censorRequestHeaders = [
            'API-KEY',
            'Authorization',
            'Cookie',
            'Set-Cookie',
            'X-CSRF-TOKEN',
            'X-XSRF-TOKEN',
        ],
        bool $removeRequestIp = false,
    ): self {
        $this->middleware[AddRequestInformation::class] = [
            'censor_body_fields' => $censorBodyFields,
            'censor_request_headers' => $censorRequestHeaders,
            'remove_requestIp' => $removeRequestIp,
        ];

        return $this;
    }

    public function addConsoleInfo(): self
    {
        $this->middleware[AddConsoleInformation::class] = [];

        return $this;
    }

    public function addGitInfo(): static
    {
        $this->middleware[AddGitInformation::class] = [];

        return $this;
    }

    public function addGlows(
        bool $traceGlows = true,
        bool $reportGlows = true,
        ?int $maxReportedGlows = 10,
    ): static {
        $this->recorders[GlowRecorder::class] = [
            'trace_glows' => $traceGlows,
            'report_glows' => $reportGlows,
            'max_reported_glows' => $maxReportedGlows,
        ];

        return $this;
    }

    public function addSolutions(): static
    {
        $this->middleware[AddSolutions::class] = [];

        return $this;
    }

    public function addDumps(
        bool $traceDumps = true,
        bool $reportDumps = true,
        ?int $maxReportedDumps = 25,
        bool $findDumpOrigins = true,
    ): static {
        $this->recorders[DumpRecorder::class] = [
            'trace_dumps' => $traceDumps,
            'report_dumps' => $reportDumps,
            'max_reported_dumps' => $maxReportedDumps,
            'find_dump_origins' => $findDumpOrigins,
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

    public function addStackFrameArguments(bool $withStackFrameArguments = true, bool $forcePHPIniSetting = true): static
    {
        $this->withStackFrameArguments = $withStackFrameArguments;
        $this->forcePHPStackFrameArgumentsIniSetting = $forcePHPIniSetting;

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

    public function reportErrorLevels(int $reportErrorLevels): static
    {
        $this->reportErrorLevels = $reportErrorLevels;

        return $this;
    }

    /**
     * @param array<class-string<ArgumentReducer>|ArgumentReducer> $argumentReducer
     */
    public function usingArgumentReducer(
        string|ArgumentReducer ...$argumentReducer
    ): static {
        if ($this->argumentReducers === null) {
            $this->argumentReducers = [];
        }

        if (! is_array($this->argumentReducers)) {
            throw new Exception('Argument reducers already set');
        }

        foreach ($argumentReducer as $reducer) {
            $this->argumentReducers[] = $reducer;
        }

        return $this;
    }

    public function setArgumentReducers(ArgumentReducers $argumentReducers): static
    {
        $this->argumentReducers = $argumentReducers;

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

    public function sendReportsImmediately(bool $sendReportsImmediately = true): static
    {
        $this->sendReportsImmediately = $sendReportsImmediately;

        return $this;
    }
}
