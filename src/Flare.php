<?php

namespace Spatie\FlareClient;

use Closure;
use Error;
use ErrorException;
use Exception;
use Illuminate\Pipeline\Pipeline;
use Psr\Container\ContainerInterface;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\Backtrace\Arguments\Reducers\ArgumentReducer;
use Spatie\FlareClient\Concerns\HasContext;
use Spatie\FlareClient\Context\ContextProviderDetector;
use Spatie\FlareClient\Enums\MessageLevels;
use Spatie\FlareClient\FlareMiddleware\ContainerAwareFlareMiddleware;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\Http\Client;
use Spatie\FlareClient\Performance\Tracer;
use Spatie\FlareClient\Recorders\DumpRecorder\DumpRecorder;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowSpanEvent;
use Spatie\FlareClient\Support\Container;
use Throwable;

class Flare
{
    use HasContext;

    protected mixed $previousExceptionHandler = null;

    protected mixed $previousErrorHandler = null;

    /**
     * @param array<int, FlareMiddleware> $middleware
     * @param null|Closure(Exception): bool $filterExceptionsCallable
     * @param null|Closure(Report): bool $filterReportsCallable
     * @param array<class-string<ArgumentReducer>|ArgumentReducer>|ArgumentReducers|null $argumentReducers
     */
    public function __construct(
        protected ContainerInterface $container,
        protected Api $api,
        public readonly Client $client,
        public readonly Tracer $tracer,
        protected ?string $applicationPath,
        protected ContextProviderDetector $contextProviderDetector,
        protected array $middleware,
        protected string $applicationName,
        protected ?string $applicationVersion,
        ?string $stage,
        protected ?int $reportErrorLevels,
        protected null|Closure $filterExceptionsCallable,
        protected null|Closure $filterReportsCallable,
        protected null|array|ArgumentReducers $argumentReducers,
        protected bool $withStackFrameArguments,
    ) {
        $this->stage = $stage;

        foreach ($this->middleware as $singleMiddleware) {
            if ($singleMiddleware instanceof ContainerAwareFlareMiddleware) {
                $singleMiddleware->boot($container);
            }
        }
    }

    public static function make(
        string $apiToken,
    ): self {
        return self::makeFromConfig(
            FlareConfig::makeDefault($apiToken)
        );
    }

    public static function makeFromConfig(FlareConfig $config): self
    {
        $container = Container::instance();

        (new FlareProvider())->register($config, $container);

        return $container->get(Flare::class);
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

    public function registerErrorHandler(?int $errorLevels = null): self
    {
        $this->previousErrorHandler = $errorLevels
            ? set_error_handler([$this, 'handleError'], $errorLevels)
            : set_error_handler([$this, 'handleError']);

        return $this;
    }

    /**
     * @param string $name
     * @param string $messageLevel
     * @param array<int, mixed> $metaData
     *
     * @return $this
     */
    public function glow(
        string $name,
        string $messageLevel = MessageLevels::INFO,
        array $metaData = []
    ): self {
        if (! $this->container->has(GlowRecorder::class)) {
            return $this;
        }

        $this->container->get(GlowRecorder::class)->record(new GlowSpanEvent($name, $messageLevel, $metaData));

        return $this;
    }

    public function handleException(Throwable $throwable): void
    {
        $this->report($throwable);

        if ($this->previousExceptionHandler && is_callable($this->previousExceptionHandler)) {
            call_user_func($this->previousExceptionHandler, $throwable);
        }
    }

    /**
     * @return mixed
     */
    public function handleError(mixed $code, string $message, string $file = '', int $line = 0)
    {
        $exception = new ErrorException($message, 0, $code, $file, $line);

        $this->report($exception);

        if ($this->previousErrorHandler) {
            return call_user_func(
                $this->previousErrorHandler,
                $code,
                $message,
                $file,
                $line
            );
        }
    }

    public function report(Throwable $throwable, callable $callback = null, Report $report = null, ?bool $handled = null): ?Report
    {
        if (! $this->shouldSendReport($throwable)) {
            return null;
        }

        $report ??= $this->createReport($throwable);

        if ($handled) {
            $report->handled();
        }

        if (! is_null($callback)) {
            call_user_func($callback, $report);
        }
        $this->resetRecorders();

        $this->sendReportToApi($report);

        return $report;
    }

    public function reportHandled(Throwable $throwable): ?Report
    {
        return $this->report($throwable, null, null, true);
    }

    protected function shouldSendReport(Throwable $throwable): bool
    {
        if (isset($this->reportErrorLevels) && $throwable instanceof Error) {
            return (bool) ($this->reportErrorLevels & $throwable->getCode());
        }

        if (isset($this->reportErrorLevels) && $throwable instanceof ErrorException) {
            return (bool) ($this->reportErrorLevels & $throwable->getSeverity());
        }

        if ($this->filterExceptionsCallable && $throwable instanceof Exception) {
            return (bool) (call_user_func($this->filterExceptionsCallable, $throwable));
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
        if ($this->filterReportsCallable) {
            if (! call_user_func($this->filterReportsCallable, $report)) {
                return;
            }
        }

        try {
            $this->api->report($report);
        } catch (Exception $exception) {
        }
    }

    public function reset(): void
    {
        $this->api->sendQueuedReports();

        $this->userProvidedContext = [];

        $this->resetRecorders();
    }

    protected function applyAdditionalParameters(Report $report): void
    {
        $report
            ->stage($this->stage)
            ->messageLevel($this->messageLevel)
            ->setApplicationPath($this->applicationPath)
            ->userProvidedContext($this->userProvidedContext);
    }

    public function createReport(Throwable $throwable): Report
    {
        $report = Report::createForThrowable(
            $throwable,
            $this->contextProviderDetector->detectCurrentContext(),
            $this->applicationPath,
            $this->applicationVersion,
            $this->argumentReducers,
            $this->withStackFrameArguments
        );

        return $this->applyMiddlewareToReport($report);
    }

    public function createReportFromMessage(string $message, string $logLevel): Report
    {
        $report = Report::createForMessage(
            $message,
            $logLevel,
            $this->contextProviderDetector->detectCurrentContext(),
            $this->applicationPath,
            $this->argumentReducers,
            $this->withStackFrameArguments
        );

        return $this->applyMiddlewareToReport($report);
    }

    protected function applyMiddlewareToReport(Report $report): Report
    {
        $this->applyAdditionalParameters($report);
        $middleware = array_map(function ($singleMiddleware) {
            return is_string($singleMiddleware)
                ? new $singleMiddleware
                : $singleMiddleware;
        }, $this->middleware);


        // TODO: let's completely remove Laravel and implement this in a more generic way
        $report = (new Pipeline())
            ->send($report)
            ->through($middleware)
            ->then(fn ($report) => $report);

        return $report;
    }

    protected function resetRecorders(): void
    {
        // TODO: this should be done in the middleware or we need to solve this in the container?
        // probably also dependent on Laravel implementation
        if ($this->container->has(GlowRecorder::class)) {
            $this->container->get(GlowRecorder::class)->reset();
        }

        if ($this->container->has(DumpRecorder::class)) {
            $this->container->get(DumpRecorder::class)->reset();
        }
    }
}
