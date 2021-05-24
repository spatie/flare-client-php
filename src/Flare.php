<?php

namespace Spatie\FlareClient;

use Closure;
use Error;
use ErrorException;
use Exception;
use Illuminate\Contracts\Container\Container;
use Illuminate\Pipeline\Pipeline;
use Spatie\FlareClient\Concerns\HasContext;
use Spatie\FlareClient\Context\ContextContextDetector;
use Spatie\FlareClient\Context\ContextDetectorInterface;
use Spatie\FlareClient\Enums\MessageLevels;
use Spatie\FlareClient\Glows\Glow;
use Spatie\FlareClient\Glows\Recorder;
use Spatie\FlareClient\Http\Client;
use Spatie\FlareClient\Middleware\AddGlows;
use Spatie\FlareClient\Middleware\AnonymizeIp;
use Spatie\FlareClient\Middleware\CensorRequestBodyFields;
use Throwable;

class Flare
{
    use HasContext;

    protected Client $client;

    protected Api $api;

    protected array $middleware = [];

    protected Recorder $recorder;

    protected ?string $applicationPath = null;

    protected ?Container $container = null;

    protected ContextDetectorInterface $contextDetector;

    protected ?Closure $previousExceptionHandler = null;

    protected ?Closure $previousErrorHandler = null;

    protected ?Closure $determineVersionCallable = null;

    protected ?int $reportErrorLevels = null;

    protected ?Closure $filterExceptionsCallable = null;

    protected ?string $stage = null;

    public static function make(
        string $apiKey = null,
        string $apiSecret = null,
        ContextDetectorInterface $contextDetector = null,
        Container $container = null
    ): self {
        $client = new Client($apiKey, $apiSecret);

        return new static($client, $contextDetector, $container);
    }

    public function setApiToken(string $apiToken): self
    {
        $this->client->setApiToken($apiToken);

        return $this;
    }

    public function setApiSecret(string $apiSecret): self
    {
        $this->client->setApiSecret($apiSecret);

        return $this;
    }

    public function setBaseUrl(string $baseUrl): self
    {
        $this->client->setBaseUrl($baseUrl);

        return $this;
    }

    public function setStage(string $stage): self
    {
        $this->stage = $stage;

        return $this;
    }

    public function determineVersionUsing(callable $determineVersionCallable)
    {
        $this->determineVersionCallable = $determineVersionCallable;
    }

    public function reportErrorLevels(int $reportErrorLevels)
    {
        $this->reportErrorLevels = $reportErrorLevels;
    }

    public function filterExceptionsUsing(callable $filterExceptionsCallable)
    {
        $this->filterExceptionsCallable = $filterExceptionsCallable;
    }

    public function version(): ?string
    {
        if (! $this->determineVersionCallable) {
            return null;
        }

        return ($this->determineVersionCallable)();
    }

    public function __construct(
        Client $client,
        ContextDetectorInterface $contextDetector = null,
        Container $container = null,
        array $middleware = []
    ) {
        $this->client = $client;
        $this->recorder = new Recorder();
        $this->contextDetector = $contextDetector ?? new ContextContextDetector();
        $this->container = $container;
        $this->middleware = $middleware;
        $this->api = new Api($this->client);

        $this->registerDefaultMiddleware();
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function setContextDectector(ContextDetectorInterface $contextDetector): self
    {
        $this->contextDetector = $contextDetector;

        return $this;
    }

    public function setContainer(Container $container): self
    {
        $this->container = $container;

        return $this;
    }

    public function registerFlareHandlers(): self
    {
        $this->registerExceptionHandler();

        $this->registerErrorHandler();

        return $this;
    }

    public function registerExceptionHandler(): self
    {
        $this->previousExceptionHandler = set_exception_handler([$this, 'handleException']);

        return $this;
    }

    public function registerErrorHandler(): self
    {
        $this->previousErrorHandler = set_error_handler([$this, 'handleError']);

        return $this;
    }

    private function registerDefaultMiddleware(): self
    {
        return $this->registerMiddleware(new AddGlows($this->recorder));
    }

    public function registerMiddleware($callable): self
    {
        $this->middleware[] = $callable;

        return $this;
    }

    public function getMiddlewares(): array
    {
        return $this->middleware;
    }

    public function glow(
        string $name,
        string $messageLevel = MessageLevels::INFO,
        array $metaData = []
    ) {
        $this->recorder->record(new Glow($name, $messageLevel, $metaData));
    }

    public function handleException(Throwable $throwable): void
    {
        $this->report($throwable);

        if ($this->previousExceptionHandler) {
            call_user_func($this->previousExceptionHandler, $throwable);
        }
    }

    public function handleError($code, $message, $file = '', $line = 0)
    {
        $exception = new ErrorException($message, 0, $code, $file, $line);

        $this->report($exception);

        if ($this->previousErrorHandler) {
            return call_user_func(
                $this->previousErrorHandler,
                $message,
                $code,
                $file,
                $line
            );
        }
    }

    public function applicationPath(string $applicationPath): self
    {
        $this->applicationPath = $applicationPath;

        return $this;
    }

    public function report(Throwable $throwable, callable $callback = null): void
    {
        if (! $this->shouldSendReport($throwable)) {
            return;
        }

        $report = $this->createReport($throwable);

        if (! is_null($callback)) {
            call_user_func($callback, $report);
        }

        $this->sendReportToApi($report);
    }

    protected function shouldSendReport(Throwable $throwable): bool
    {
        if ($this->reportErrorLevels && $throwable instanceof Error) {
            return $this->reportErrorLevels & $throwable->getCode();
        }

        if ($this->reportErrorLevels && $throwable instanceof ErrorException) {
            return $this->reportErrorLevels & $throwable->getSeverity();
        }

        if ($this->filterExceptionsCallable && $throwable instanceof Exception) {
            return call_user_func($this->filterExceptionsCallable, $throwable);
        }

        return true;
    }

    public function reportMessage(string $message, string $logLevel, callable $callback = null): void
    {
        $report = $this->createReportFromMessage($message, $logLevel);

        if (! is_null($callback)) {
            call_user_func($callback, $report);
        }

        $this->sendReportToApi($report);
    }

    public function sendTestReport(Throwable $throwable): void
    {
        $this->api->sendTestReport($this->createReport($throwable));
    }

    protected function sendReportToApi(Report $report): void
    {
        try {
            $this->api->report($report);
        } catch (Exception $exception) {
        }
    }

    public function reset()
    {
        $this->api->sendQueuedReports();

        $this->userProvidedContext = [];

        $this->recorder->reset();
    }

    protected function applyAdditionalParameters(Report $report): void
    {
        $report
            ->stage($this->stage)
            ->messageLevel($this->messageLevel)
            ->setApplicationPath($this->applicationPath)
            ->userProvidedContext($this->userProvidedContext);
    }

    public function anonymizeIp(): self
    {
        $this->registerMiddleware(new AnonymizeIp());

        return $this;
    }

    public function censorRequestBodyFields(array $fieldNames): self
    {
        $this->registerMiddleware(new CensorRequestBodyFields($fieldNames));

        return $this;
    }

    public function createReport(Throwable $throwable): Report
    {
        $report = Report::createForThrowable(
            $throwable,
            $this->contextDetector->detectCurrentContext(),
            $this->applicationPath,
            $this->version()
        );

        return $this->applyMiddlewareToReport($report);
    }

    public function createReportFromMessage(string $message, string $logLevel): Report
    {
        $report = Report::createForMessage(
            $message,
            $logLevel,
            $this->contextDetector->detectCurrentContext(),
            $this->applicationPath
        );

        return $this->applyMiddlewareToReport($report);
    }

    protected function applyMiddlewareToReport(Report $report): Report
    {
        $this->applyAdditionalParameters($report);

        $report = (new Pipeline($this->container))
            ->send($report)
            ->through($this->middleware)
            ->then(function ($report) {
                return $report;
            });

        return $report;
    }
}
