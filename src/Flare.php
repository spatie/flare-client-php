<?php

namespace Spatie\FlareClient;

use Closure;
use Error;
use ErrorException;
use Exception;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\FlareClient\Concerns\HasCustomContext;
use Spatie\FlareClient\Contracts\Recorders\Recorder;
use Spatie\FlareClient\Enums\OverriddenGrouping;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\Recorders\CacheRecorder\CacheRecorder;
use Spatie\FlareClient\Recorders\CommandRecorder\CommandRecorder;
use Spatie\FlareClient\Recorders\FilesystemRecorder\FilesystemRecorder;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Spatie\FlareClient\Recorders\LogRecorder\LogRecorder;
use Spatie\FlareClient\Recorders\NullRecorder;
use Spatie\FlareClient\Recorders\QueryRecorder\QueryRecorder;
use Spatie\FlareClient\Recorders\ThrowableRecorder\ThrowableRecorder;
use Spatie\FlareClient\Recorders\TransactionRecorder\TransactionRecorder;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\Container;
use Spatie\FlareClient\Support\SentReports;
use Spatie\FlareClient\Support\StacktraceMapper;
use Throwable;

class Flare
{
    use HasCustomContext;

    protected mixed $previousExceptionHandler = null;

    protected mixed $previousErrorHandler = null;

    /**
     * @param array<int, FlareMiddleware> $middleware
     * @param array<string, Recorder> $recorders
     * @param null|Closure(Exception): bool $filterExceptionsCallable
     * @param null|Closure(Report): bool $filterReportsCallable
     * @param ArgumentReducers|null $argumentReducers
     * @param array<class-string, OverriddenGrouping> $overriddenGroupings
     */
    public function __construct(
        protected readonly Api $api,
        public readonly Tracer $tracer,
        public readonly BackTracer $backTracer,
        protected readonly SentReports $sentReports,
        protected readonly array $middleware,
        protected readonly array $recorders,
        protected readonly ?ThrowableRecorder $throwableRecorder,
        protected readonly ?string $applicationPath,
        protected readonly ?int $reportErrorLevels,
        protected null|Closure $filterExceptionsCallable,
        protected null|Closure $filterReportsCallable,
        protected readonly null|ArgumentReducers $argumentReducers,
        protected readonly bool $withStackFrameArguments,
        protected Resource $resource,
        protected Scope $scope,
        protected StacktraceMapper $stacktraceMapper,
        protected array $overriddenGroupings
    ) {
    }

    public static function make(
        string $apiToken,
    ): self {
        return self::makeFromConfig(
            FlareConfig::make($apiToken)->useDefaults()
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

    public function startRecorders(): self
    {
        foreach ($this->recorders as $recorder) {
            $recorder->start();
        }

        return $this;
    }

    // TODO: ensure we've got all the recorders here

    public function command(): CommandRecorder|NullRecorder
    {
        return$this->recorders[RecorderType::Command->value] ?? NullRecorder::instance();
    }

    public function cache(): CacheRecorder|NullRecorder
    {
        return $this->recorders[RecorderType::Cache->value] ?? NullRecorder::instance();
    }

    public function glow(): GlowRecorder|NullRecorder
    {
        return $this->recorders[RecorderType::Glow->value] ?? NullRecorder::instance();
    }

    public function log(): LogRecorder|NullRecorder
    {
        return $this->recorders[RecorderType::Log->value] ?? NullRecorder::instance();
    }

    public function filesystem(): FilesystemRecorder|NullRecorder
    {
        return $this->recorders[RecorderType::Filesystem->value] ?? NullRecorder::instance();
    }

    public function query(): QueryRecorder|NullRecorder
    {
        return $this->recorders[RecorderType::Query->value] ?? NullRecorder::instance();
    }

    public function transaction(): TransactionRecorder|NullRecorder
    {
        return $this->recorders[RecorderType::Transaction->value] ?? NullRecorder::instance();
    }

    public function span(
        string $name,
        Closure $closure,
        array $attributes = [],
    ): Span {
        $span = $this->tracer->startSpan($name, attributes: $attributes);

        $closure();

        return $this->tracer->endSpan($span);
    }

    public function spanEvent(
        string $name,
        array $attributes = [],
    ): ?SpanEvent {
        if ($this->tracer->currentSpan() === null) {
            return null;
        }

        $event = SpanEvent::build($name, attributes: $attributes);

        $this->tracer->currentSpan()->addEvent(
            $event
        );

        return $event;
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

        $report = $this->createReport($throwable, $callback, $handled);

        if ($this->throwableRecorder) {
            $this->throwableRecorder->record($report);
        }

        $this->resetRecorders();

        $this->sentReports->add($report);

        $this->sendReportToApi($report);

        return $report;
    }

    public function createReport(
        Throwable $throwable,
        callable $callback = null,
        ?bool $handled = null
    ): Report {
        $factory = $this->feedReportFactory(
            ReportFactory::createForThrowable($throwable),
            $callback
        );

        if ($handled) {
            $factory->handled();
        }

        return $factory->build($this->stacktraceMapper);
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
    public function reportMessage(
        string $message,
        string $logLevel,
        callable $callback = null,
    ): Report {
        $factory = $this->feedReportFactory(
            ReportFactory::createForMessage($message, $logLevel),
            $callback
        );

        $report = $factory->build($this->stacktraceMapper);

        $this->sendReportToApi($report);

        return $report;
    }

    public function sendTestReport(Throwable $throwable): void
    {
        $this->api->test(
            ReportFactory::createForThrowable($throwable)->resource($this->resource)->build($this->stacktraceMapper),
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

    public function withApplicationVersion(string|Closure $version): self
    {
        $this->resource->serviceVersion(is_callable($version) ? $version() : $version);

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

    public function sendReportsImmediately(bool $sendReportsImmediately = true): self
    {
        $this->api->sendReportsImmediately($sendReportsImmediately);

        return $this;
    }

    public function resetReporting(): void
    {
        $this->api->sendQueue();

        $this->customContext = [];

        $this->resetRecorders();
        $this->sentReports->clear();
    }

    public function sentReports(): SentReports
    {
        return $this->sentReports;
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
            ->resource($this->resource)
            ->withStackTraceArguments($this->withStackFrameArguments)
            ->argumentReducers($this->argumentReducers)
            ->overriddenGroupings($this->overriddenGroupings)
            ->context($this->customContext);

        foreach ($this->middleware as $middleware) {
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
