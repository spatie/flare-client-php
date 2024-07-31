<?php

namespace Spatie\FlareClient;

use Closure;
use Error;
use ErrorException;
use Exception;
use Psr\Container\ContainerInterface;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\Backtrace\Arguments\Reducers\ArgumentReducer;
use Spatie\FlareClient\Concerns\HasUserProvidedContext;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Contracts\Recorder;
use Spatie\FlareClient\Enums\MessageLevels;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowSpanEvent;
use Spatie\FlareClient\Recorders\LogRecorder\LogMessageSpanEvent;
use Spatie\FlareClient\Recorders\LogRecorder\LogRecorder;
use Spatie\FlareClient\Recorders\QueryRecorder\QueryRecorder;
use Spatie\FlareClient\Support\Container;
use Throwable;

class Flare
{
    use HasUserProvidedContext;

    protected mixed $previousExceptionHandler = null;

    protected mixed $previousErrorHandler = null;

    /**
     * @param array<int, FlareMiddleware> $middleware
     * @param array<class-string<Recorder>, Recorder> $recorders
     * @param null|Closure(Exception): bool $filterExceptionsCallable
     * @param null|Closure(ReportFactory): bool $filterReportsCallable
     * @param array<class-string<ArgumentReducer>|ArgumentReducer>|ArgumentReducers|null $argumentReducers
     */
    public function __construct(
        protected ContainerInterface $container,
        protected Api $api,
        public readonly Tracer $tracer,
        protected array $middleware,
        protected array $recorders,
        protected ?string $applicationPath,
        protected string $applicationName,
        protected ?string $applicationVersion,
        protected ?string $applicationStage,
        protected ?int $reportErrorLevels,
        protected null|Closure $filterExceptionsCallable,
        protected null|Closure $filterReportsCallable,
        protected null|array|ArgumentReducers $argumentReducers,
        protected bool $withStackFrameArguments,
    ) {
    }

    public static function make(
        string $apiToken,
    ): self {
        return self::makeFromConfig(
            FlareConfig::makeDefault($apiToken)
        );
    }

    public static function makeFromConfig(FlareConfig $config, bool $boot = true): self
    {
        $container = Container::instance();

        $provider = new FlareProvider($config, $container);

        $provider->register();

        if ($boot) {
            $provider->boot();
        }

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
        /** @var GlowRecorder $recorder */
        $recorder = $this->recorders[GlowRecorder::class] ?? null;

        if ($recorder === null) {
            return $this;
        }

        $recorder->record($name, $messageLevel, $metaData);

        return $this;
    }

    /**
     * @param string $name
     * @param string $messageLevel
     * @param array<int, mixed> $metaData
     *
     * @return $this
     */
    public function log(
        string $message,
        string $level = MessageLevels::INFO,
        array $context = []
    ): self {
        /** @var LogRecorder $recorder */
        $recorder = $this->recorders[LogRecorder::class] ?? null;

        if ($recorder === null) {
            return $this;
        }

        $recorder->record($message, $level, $context);

        return $this;
    }

    public function query(
        string $sql,
        int $duration,
        ?array $bindings = null,
        ?string $databaseName = null,
        ?string $driverName = null,
        FlareSpanType $spanType = SpanType::Query,
        ?array $attributes = null,
    ): self {
        /** @var QueryRecorder $recorder */
        $recorder = $this->recorders[QueryRecorder::class] ?? null;

        if ($recorder === null) {
            return $this;
        }

        $recorder->record(
            sql: $sql,
            duration: $duration,
            bindings: $bindings,
            databaseName: $databaseName,
            driverName: $driverName,
            spanType: $spanType,
            attributes: $attributes
        );

        return $this;
    }

    public function handleException(Throwable $throwable): void
    {
        $this->report($throwable);

        if ($this->previousExceptionHandler && is_callable($this->previousExceptionHandler)) {
            call_user_func($this->previousExceptionHandler, $throwable);
        }
    }

    public function handleError(mixed $code, string $message, string $file = '', int $line = 0): void
    {
        $exception = new ErrorException($message, 0, $code, $file, $line);

        $this->report($exception);

        if ($this->previousErrorHandler) {
            call_user_func(
                $this->previousErrorHandler,
                $code,
                $message,
                $file,
                $line
            );
        }
    }

    /**
     * @param callable(ReportFactory $report):void|null $callback
     */
    public function report(
        Throwable $throwable,
        callable $callback = null,
        ?bool $handled = null
    ): ?Report {
        if (! $this->shouldSendReport($throwable)) {
            return null;
        }

        $factory = $this->feedReportFactory(
            ReportFactory::createForThrowable($throwable),
            $callback
        );

        if ($handled) {
            $factory->handled();
        }

        $this->resetRecorders();

        $report = $factory->build();

        $this->sendReportToApi($report);

        return $report;
    }

    public function reportHandled(Throwable $throwable): ?Report
    {
        return $this->report($throwable, handled: true);
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

    /**
     * @param callable(ReportFactory $report): void|null $callback
     */
    public function reportMessage(string $message, string $logLevel, callable $callback = null): Report
    {
        $factory = $this->feedReportFactory(
            ReportFactory::createForMessage($message, $logLevel),
            $callback
        );

        $report = $factory->build();

        $this->sendReportToApi($report);

        return $report;
    }

    public function sendTestReport(Throwable $throwable): void
    {
        $this->api->report(
            ReportFactory::createForThrowable($throwable)->build(),
            immediately: true
        );
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
        $this->api->sendQueue();

        $this->userProvidedContext = [];

        $this->resetRecorders();
    }

    public function resetRecorders(): void
    {
        foreach ($this->recorders as $recorder) {
            $recorder->reset();
        }
    }

    /**
     * @param callable(ReportFactory $report): void|null $callback
     */
    protected function feedReportFactory(
        ReportFactory $factory,
        ?callable $callback
    ): ReportFactory {
        $factory
            ->applicationStage($this->applicationStage)
            ->applicationPath($this->applicationPath)
            ->applicationVersion($this->applicationVersion)
            ->languageVersion(phpversion())
            ->withStackTraceArguments($this->withStackFrameArguments)
            ->argumentReducers($this->argumentReducers)
            ->context($this->userProvidedContext);

        /** @var array<FlareMiddleware> $middlewares */
        $middlewares = array_map(function ($singleMiddleware) {
            return is_string($singleMiddleware)
                ? new $singleMiddleware
                : $singleMiddleware;
        }, $this->middleware);

        // TODO: not sure about this working?
        foreach ($middlewares as $middleware) {
            $factory = $middleware->handle($factory, function ($factory) {
                return $factory;
            });
        }

        if (! is_null($callback)) {
            call_user_func($callback, $factory);
        }

        return $factory;
    }
}
