<?php

namespace Spatie\FlareClient;

use Closure;
use Error;
use ErrorException;
use Exception;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\ErrorSolutions\Contracts\HasSolutionsForThrowable;
use Spatie\ErrorSolutions\Contracts\SolutionProviderRepository;
use Spatie\FlareClient\Concerns\HasCustomContext;
use Spatie\FlareClient\Contracts\Recorders\Recorder;
use Spatie\FlareClient\Enums\OverriddenGrouping;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\Recorders\ApplicationRecorder\ApplicationRecorder;
use Spatie\FlareClient\Recorders\CacheRecorder\CacheRecorder;
use Spatie\FlareClient\Recorders\CommandRecorder\CommandRecorder;
use Spatie\FlareClient\Recorders\ErrorRecorder\ErrorRecorder;
use Spatie\FlareClient\Recorders\ExternalHttpRecorder\ExternalHttpRecorder;
use Spatie\FlareClient\Recorders\FilesystemRecorder\FilesystemRecorder;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Spatie\FlareClient\Recorders\LogRecorder\LogRecorder;
use Spatie\FlareClient\Recorders\QueryRecorder\QueryRecorder;
use Spatie\FlareClient\Recorders\RedisCommandRecorder\RedisCommandRecorder;
use Spatie\FlareClient\Recorders\RequestRecorder\RequestRecorder;
use Spatie\FlareClient\Recorders\ResponseRecorder\ResponseRecorder;
use Spatie\FlareClient\Recorders\RoutingRecorder\RoutingRecorder;
use Spatie\FlareClient\Recorders\TransactionRecorder\TransactionRecorder;
use Spatie\FlareClient\Recorders\ViewRecorder\ViewRecorder;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\Container;
use Spatie\FlareClient\Support\Ids;
use Spatie\FlareClient\Support\SentReports;
use Spatie\FlareClient\Support\StacktraceMapper;
use Spatie\FlareClient\Time\Time;
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
        protected readonly Ids $ids,
        protected readonly Time $time,
        protected readonly SentReports $sentReports,
        protected readonly array $middleware,
        protected readonly array $recorders,
        protected readonly ?ErrorRecorder $throwableRecorder,
        protected readonly ?int $reportErrorLevels,
        protected null|Closure $filterExceptionsCallable,
        protected null|Closure $filterReportsCallable,
        protected readonly SolutionProviderRepository $solutionProviderRepository,
        protected readonly null|ArgumentReducers $argumentReducers,
        protected readonly bool $collectStackFrameArguments,
        protected Resource $resource,
        protected Scope $scope,
        protected StacktraceMapper $stacktraceMapper,
        protected ?string $applicationPath,
        protected array $overriddenGroupings,
        protected bool $includeStackTraceWithMessages,
    ) {
    }

    public static function make(
        string|FlareConfig $apiToken,
    ): self {
        $config = is_string($apiToken) ? FlareConfig::make($apiToken)->useDefaults() : $apiToken;

        $container = Container::instance();

        $provider = new FlareProvider($config, $container);

        $provider->register();
        $provider->boot();

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

    public function bootRecorders(): self
    {
        foreach ($this->recorders as $recorder) {
            $recorder->boot();
        }

        return $this;
    }

    public function tracer(): Tracer
    {
        return $this->tracer;
    }

    public function backTracer(): BackTracer
    {
        return $this->backTracer;
    }

    public function application(): ApplicationRecorder
    {
        return $this->recorders[RecorderType::Application->value];
    }

    public function cache(): CacheRecorder|null
    {
        return $this->recorders[RecorderType::Cache->value] ?? null;
    }

    public function command(): CommandRecorder|null
    {
        return $this->recorders[RecorderType::Command->value] ?? null;
    }

    public function externalHttp(): ExternalHttpRecorder|null
    {
        return $this->recorders[RecorderType::ExternalHttp->value] ?? null;
    }

    public function filesystem(): FilesystemRecorder|null
    {
        return $this->recorders[RecorderType::Filesystem->value] ?? null;
    }

    public function glow(): GlowRecorder|null
    {
        return $this->recorders[RecorderType::Glow->value] ?? null;
    }

    public function log(): LogRecorder|null
    {
        return $this->recorders[RecorderType::Log->value] ?? null;
    }

    public function query(): QueryRecorder|null
    {
        return $this->recorders[RecorderType::Query->value] ?? null;
    }

    public function redisCommand(): RedisCommandRecorder|null
    {
        return $this->recorders[RecorderType::RedisCommand->value] ?? null;
    }

    public function request(): RequestRecorder|null
    {
        return $this->recorders[RecorderType::Request->value] ?? null;
    }

    public function response(): ResponseRecorder|null
    {
        return $this->recorders[RecorderType::Response->value] ?? null;
    }

    public function routing(): RoutingRecorder|null
    {
        return $this->recorders[RecorderType::Routing->value] ?? null;
    }

    public function transaction(): TransactionRecorder|null
    {
        return $this->recorders[RecorderType::Transaction->value] ?? null;
    }

    public function view(): ViewRecorder|null
    {
        return $this->recorders[RecorderType::View->value] ?? null;
    }

    public function recorder(
        RecorderType|string $type
    ): Recorder {
        return $this->recorders[is_string($type) ? $type : $type->value];
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
        ?callable $callback = null,
        ?bool $handled = null
    ): ?Report {
        if (! $this->shouldSendReport($throwable)) {
            $this->tracer->gracefullyHandleError();

            return null;
        }

        $report = $this->createReport($throwable, $callback, $handled);

        if ($this->throwableRecorder) {
            $this->throwableRecorder->record($report);
        }

        $this->tracer->gracefullyHandleError();

        $this->resetRecorders();

        $this->sentReports->add($report);

        $this->sendReportToApi($report);

        return $report;
    }

    public function createReport(
        Throwable $throwable,
        ?callable $callback = null,
        ?bool $handled = null
    ): Report {
        $factory = $this->feedReportFactory(
            ReportFactory::createForThrowable($throwable),
            $callback
        );

        if ($handled) {
            $factory->handled();
        }

        return $factory->build(
            $this->stacktraceMapper,
            $this->time,
            $this->ids,
        );
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
        ?callable $callback = null,
    ): Report {
        $factory = $this->feedReportFactory(
            ReportFactory::createForMessage($message, $logLevel),
            $callback
        );

        $report = $factory->build(
            $this->stacktraceMapper,
            $this->time,
            $this->ids,
        );

        $this->sendReportToApi($report);

        return $report;
    }

    public function sendTestReport(Throwable $throwable): void
    {
        $this->api->test(
            ReportFactory::createForThrowable($throwable)->resource($this->resource)->build(
                $this->stacktraceMapper,
                $this->time,
                $this->ids,
            ),
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

    public function withApplicationName(string|Closure $name): self
    {
        $this->resource->serviceName(is_callable($name) ? $name() : $name);

        return $this;
    }

    public function withApplicationStage(string|Closure $stage): self
    {
        $this->resource->serviceStage(is_callable($stage) ? $stage() : $stage);

        return $this;
    }

    /**
     * @param class-string<HasSolutionsForThrowable> ...$solutionProviders
     */
    public function withSolutionProvider(string ...$solutionProviders): self
    {
        $this->solutionProviderRepository->registerSolutionProviders($solutionProviders);

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

    public function reset(
        bool $reports = true,
        bool $traces = true,
        bool $clearCustomContext = true
    ): void {
        $this->api->sendQueue(reports: $reports, traces: $traces);

        if ($clearCustomContext) {
            $this->clearCustomContext();
        }

        if ($reports) {
            $this->resetRecorders();
            $this->sentReports->clear();
        }
    }

    public function sentReports(): SentReports
    {
        return $this->sentReports;
    }

    protected function resetRecorders(): void
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
            ->collectStackTraceArguments($this->collectStackFrameArguments)
            ->argumentReducers($this->argumentReducers)
            ->overriddenGroupings($this->overriddenGroupings)
            ->applicationPath($this->applicationPath)
            ->includeStackTraceWithMessages($this->includeStackTraceWithMessages)
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
