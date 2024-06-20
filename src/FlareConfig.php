<?php

namespace Spatie\FlareClient;

use Closure;
use Exception;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\Backtrace\Arguments\Reducers\ArgumentReducer;
use Spatie\ErrorSolutions\Contracts\HasSolutionsForThrowable;
use Spatie\ErrorSolutions\SolutionProviderRepository;
use Spatie\FlareClient\Context\ContextProviderDetector;
use Spatie\FlareClient\FlareMiddleware\AddDumps;
use Spatie\FlareClient\FlareMiddleware\AddEnvironmentInformation;
use Spatie\FlareClient\FlareMiddleware\AddGitInformation;
use Spatie\FlareClient\FlareMiddleware\AddGlows;
use Spatie\FlareClient\FlareMiddleware\AddNotifierName;
use Spatie\FlareClient\FlareMiddleware\AddSolutions;
use Spatie\FlareClient\FlareMiddleware\CensorRequestBodyFields;
use Spatie\FlareClient\FlareMiddleware\CensorRequestHeaders;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\FlareMiddleware\RemoveRequestIp;
use Spatie\FlareClient\Http\Client;
use Spatie\FlareClient\Senders\CurlSender;
use Spatie\FlareClient\Senders\Sender;

class FlareConfig
{
    /**
     * @param array<FlareMiddleware> $middleware
     * @param array<class-string> $recorders
     * @param class-string<ContextProviderDetector>|null $contextProviderDetector
     * @param array<class-string<ArgumentReducer>|ArgumentReducer>|ArgumentReducers|null $argumentReducers
     * @param class-string<Sender> $sender
     * @param class-string<SolutionProviderRepository> $solutionsProviderRepository
     * @param array<class-string<HasSolutionsForThrowable>> $solutionsProviders
     */
    public function __construct(
        public string $apiToken,
        public string $baseUrl = 'https://reporting.flareapp.io/api',
        public int $timeout = 10,
        public bool $sendReportsImmediately = false,
        public array $middleware = [],
        public array $recorders = [],
        public ?string $applicationPath = null,
        public ?string $contextProviderDetector = null,
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
        public string $solutionsProviderRepository = SolutionProviderRepository::class,
        public array $solutionsProviders = [],
        public ?Client $client = null,
    ) {
        $this->withNotifierName();
    }

    public static function make(string $apiToken): self
    {
        return new self($apiToken);
    }

    public static function makeDefault(string $apiToken): self
    {
        return (new self($apiToken))
            ->withDumps()
            ->withEnvironmentInfo()
            ->withGitInfo()
            ->withGlows()
            ->withSolutions()
            ->censorRequestBodyFields()
            ->censorRequestHeaders()
            ->removeRequestIp()
            ->withStackFrameArguments()
            ->setArgumentReducers(ArgumentReducers::default());
    }

    public function withDumps(
        int $maxDumps = 300,
        bool $traceDumps = false,
        bool $traceDumpOrigins = false
    ): self {
        $this->middleware[] = new AddDumps($maxDumps, $traceDumps, $traceDumpOrigins);

        return $this;
    }

    public function withEnvironmentInfo(): self
    {
        $this->middleware[] = new AddEnvironmentInformation();

        return $this;
    }

    public function withGitInfo(): self
    {
        $this->middleware[] = new AddGitInformation();

        return $this;
    }

    public function withGlows(
        int $maxGlows = 30,
        bool $traceGlows = false
    ): self {
        $this->middleware[] = new AddGlows($maxGlows, $traceGlows);

        return $this;
    }

    public function withNotifierName(): self
    {
        $this->middleware[] = new AddNotifierName();

        return $this;
    }

    public function withSolutions(): self
    {
        $this->middleware[] = new AddSolutions();

        return $this;
    }

    public function censorRequestBodyFields(array $fieldNames = ['password', 'password_confirmation']): self
    {
        $this->middleware[] = new CensorRequestBodyFields($fieldNames);

        return $this;
    }

    public function censorRequestHeaders(
        array $headers = [
            'API-KEY',
            'Authorization',
            'Cookie',
            'Set-Cookie',
            'X-CSRF-TOKEN',
            'X-XSRF-TOKEN',
        ]
    ): self {
        $this->middleware[] = new CensorRequestHeaders($headers);

        return $this;
    }

    public function removeRequestIp(): self
    {
        $this->middleware[] = new RemoveRequestIp();

        return $this;
    }

    /**
     * @param string|Closure(): string $version
     */
    public function applicationVersion(string|Closure $version): self
    {
        $this->applicationVersion = is_callable($version) ? $version() : $version;

        return $this;
    }

    /**
     * @param string|Closure(): string $name
     */
    public function applicationName(string|Closure $name): self
    {
        $this->applicationName = is_callable($name) ? $name() : $name;

        return $this;
    }

    /**
     * @param string|Closure(): string $stage
     */
    public function applicationStage(string|Closure $stage): self
    {
        $this->applicationStage = is_callable($stage) ? $stage() : $stage;

        return $this;
    }

    public function withStackFrameArguments(bool $withStackFrameArguments = true, bool $forcePHPIniSetting = true): self
    {
        $this->withStackFrameArguments = $withStackFrameArguments;
        $this->forcePHPStackFrameArgumentsIniSetting = $forcePHPIniSetting;

        return $this;
    }

    /**
     * @param Closure(Exception): bool $filterExceptionsCallable
     */
    public function filterExceptionsUsing(Closure $filterExceptionsCallable): self
    {
        $this->filterExceptionsCallable = $filterExceptionsCallable;

        return $this;
    }

    public function reportErrorLevels(int $reportErrorLevels): self
    {
        $this->reportErrorLevels = $reportErrorLevels;

        return $this;
    }

    /**
     * @param array<class-string<ArgumentReducer>|ArgumentReducer> $argumentReducer
     */
    public function withArgumentReducer(
        string|ArgumentReducer ...$argumentReducer
    ): self {
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

    public function setArgumentReducers(ArgumentReducers $argumentReducers): self
    {
        $this->argumentReducers = $argumentReducers;

        return $this;
    }
}
