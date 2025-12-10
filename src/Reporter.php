<?php

namespace Spatie\FlareClient;

use Closure;
use Error;
use ErrorException;
use Exception;
use Spatie\ErrorSolutions\Contracts\HasSolutionsForThrowable;
use Spatie\ErrorSolutions\Contracts\SolutionProviderRepository;
use Spatie\FlareClient\Contracts\Recorders\SpanEventsRecorder;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\LifecycleStage;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Enums\SpanStatusCode;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\Recorders\ContextRecorder\ContextRecorder;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\Lifecycle;
use Spatie\FlareClient\Support\Recorders;
use Spatie\FlareClient\Support\SentReports;
use Throwable;

class Reporter
{
    protected mixed $previousExceptionHandler = null;

    protected mixed $previousErrorHandler = null;

    /**
     * @param array<int, FlareMiddleware> $middleware
     * @param null|Closure(Exception): bool $filterExceptionsCallable
     * @param null|Closure(ReportFactory): bool $filterReportsCallable
     */
    public function __construct(
        protected readonly Api $api,
        protected readonly bool $disabled,
        protected readonly Tracer $tracer,
        protected readonly Lifecycle $lifecycle,
        protected readonly SentReports $sentReports,
        protected readonly ?int $reportErrorLevels,
        protected null|Closure $filterExceptionsCallable,
        protected null|Closure $filterReportsCallable,
        protected readonly SolutionProviderRepository $solutionProviderRepository,
        protected readonly ReportFactory $reportFactory,
        protected readonly array $middleware,
        protected readonly Recorders $recorders,
        protected readonly bool $addReportsToTraces,
    ) {
    }

    public function registerFlareHandlers(): self
    {
        if ($this->disabled) {
            return $this;
        }

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
     * @param Closure(ReportFactory $report):void|null $callback
     */
    public function report(
        Throwable $throwable,
        ?Closure $callback = null,
        ?bool $handled = null
    ): ?ReportFactory {
        if (! $this->shouldReport($throwable)) {
            $this->tracer->gracefullyEndSpans();

            return null;
        }

        $report = $this->createReport($throwable, $callback, $handled);

        $this->addReportToTrace($throwable, $handled, $report);

        $this->tracer->gracefullyEndSpans();


        if ($this->filterReportsCallable && ($this->filterReportsCallable)($report) === false) {
            return null;
        }

        // This is in the case we have errors before or after a lifecycle or subtask
        // Famous one is Laravel Jobs which end the subtask before the exception is handled
        $sendImmediately = $this->lifecycle->getStage() === LifecycleStage::Idle;

        $reportPayload = $this->api->report($report, $sendImmediately);

        $this->sentReports->add($reportPayload);

        return $report;
    }

    public function createReport(
        Throwable $throwable,
        ?Closure $callback = null,
        ?bool $handled = null
    ): ReportFactory {
        $factory = $this->reportFactory
            ->new()
            ->throwable($throwable);

        foreach ($this->recorders->all() as $recorder) {
            if ($recorder instanceof SpanEventsRecorder) {
                $factory->spanEvent(...$recorder->getSpanEvents());
            }

            if ($recorder instanceof SpansRecorder) {
                $factory->span(...$recorder->getSpans());
            }

            if ($recorder instanceof ContextRecorder) {
                $factory->addAttributes($recorder->toArray());
            }
        }

        foreach ($this->middleware as $middleware) {
            $factory = $middleware->handle($factory, function ($factory) {
                return $factory;
            });
        }

        if ($callback) {
            $callback($factory);
        }

        if ($handled) {
            $factory->handled();
        }

        return $factory;
    }

    public function reportHandled(Throwable $throwable): ?ReportFactory
    {
        return $this->report($throwable, handled: true);
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
     * @param Closure(ReportFactory): bool $filterReportsCallable
     */
    public function filterReportsUsing(Closure $filterReportsCallable): static
    {
        $this->filterReportsCallable = $filterReportsCallable;

        return $this;
    }

    protected function shouldReport(Throwable $throwable): bool
    {
        if ($this->disabled === true) {
            return false;
        }

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

    protected function addReportToTrace(Throwable $throwable, ?bool $handled, ReportFactory $report): void
    {
        if ($this->addReportsToTraces === false || $this->tracer->isSampling() === false) {
            return;
        }

        $currentSpan = $this->tracer->currentSpan();


        if ($currentSpan === null) {
            return;
        }

        $throwableClass = $throwable::class;

        if ($report->trackingUuid === null) {
            $report->trackingUuid($this->tracer->ids->uuid());
        }

        $event = new SpanEvent(
            name: "Exception - {$throwableClass}",
            timestamp: $this->tracer->time->getCurrentTime(),
            attributes: [
                'flare.span_event_type' => SpanEventType::Exception,
                'exception.message' => $throwable->getMessage(),
                'exception.type' => $throwableClass,
                'exception.handled' => $handled,
                'exception.id' => $report->trackingUuid,
            ]
        );

        if ($handled !== true) {
            $currentSpan->setStatus(
                SpanStatusCode::Error,
                $throwable->getMessage(),
            );
        }

        $currentSpan->addEvent($event);
    }
}
