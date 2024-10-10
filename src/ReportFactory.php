<?php

namespace Spatie\FlareClient;

use ErrorException;
use Exception;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\Backtrace\Backtrace;
use Spatie\ErrorSolutions\Contracts\Solution;
use Spatie\FlareClient\Concerns\HasAttributes;
use Spatie\FlareClient\Concerns\HasCustomContext;
use Spatie\FlareClient\Concerns\UsesIds;
use Spatie\FlareClient\Contracts\ProvidesFlareContext;
use Spatie\FlareClient\Contracts\WithAttributes;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Throwable;

class ReportFactory implements WithAttributes
{
    use HasAttributes;
    use HasCustomContext;
    use UsesIds;

    protected Resource $resource;

    public ?string $applicationPath = null;

    /** @var ArgumentReducers|null */
    public null|ArgumentReducers $argumentReducers = null;

    public bool $withStackTraceArguments = true;

    /** @var array<Span|SpanEvent> */
    public array $events = [];

    /** @var array<Solution> */
    public array $solutions = [];

    public ?string $notifierName = null;

    public ?bool $handled = null;

    public ?string $trackingUuid = null;

    protected function __construct(
        public ?Throwable $throwable = null,
        public ?string $message = null,
        public ?string $level = null,
    ) {
    }

    public static function createForMessage(string $message, string $logLevel): ReportFactory
    {
        return new self(message: $message, level: $logLevel);
    }

    public static function createForThrowable(
        Throwable $throwable,
    ): ReportFactory {
        if (! $throwable instanceof ErrorException) {
            return new self(throwable: $throwable);
        }

        $level = match ($throwable->getSeverity()) {
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
            default => 'UNKNOWN',
        };

        return new self(
            throwable: $throwable,
            level: $level
        );
    }

    public function resource(Resource $resource): self
    {
        $this->resource = $resource;

        return $this;
    }

    public function applicationPath(?string $applicationPath): self
    {
        $this->applicationPath = $applicationPath;

        return $this;
    }

    public function handled(bool $handled = true): self
    {
        $this->handled = $handled;

        return $this;
    }

    public function span(Span ...$span): self
    {
        array_push($this->events, ...$span);

        return $this;
    }

    public function spanEvent(SpanEvent ...$spanEvent): self
    {
        array_push($this->events, ...$spanEvent);

        return $this;
    }

    public function notifier(string $name): self
    {
        $this->notifierName = $name;

        return $this;
    }

    public function addSolutions(Solution ...$solution): self
    {
        array_push($this->solutions, ...$solution);

        return $this;
    }

    public function argumentReducers(null|ArgumentReducers $argumentReducers): self
    {
        $this->argumentReducers = $argumentReducers;

        return $this;
    }

    public function withStackTraceArguments(bool $withStackTraceArguments): self
    {
        $this->withStackTraceArguments = $withStackTraceArguments;

        return $this;
    }

    public function trackingUuid(string $uuid): self
    {
        $this->trackingUuid = $uuid;

        return $this;
    }

    public function build(): Report
    {
        if ($this->throwable === null && ($this->message === null || $this->level === null)) {
            throw new Exception('No throwable or message provided');
        }

        $stackTrace = $this->buildStacktrace();

        $exceptionClass = $this->throwable
            ? $this->throwable::class
            : "Log";
        $message = $this->throwable
            ? $this->throwable->getMessage()
            : $this->message;

        $attributes = array_merge(
            isset($this->resource) ? $this->resource->attributes : [],
            $this->attributes
        );

        if ($this->throwable instanceof ProvidesFlareContext) {
            $attributes['context.exception'] = array_merge(
                $attributes['context.exception'] ?? [],
                $this->throwable->context()
            );
        }

        if (! empty($this->customContext)) {
            $attributes['context.custom'] = $this->customContext;
        }

        $attributes['flare.language'] = 'PHP';

        return new Report(
            stacktrace: $stackTrace,
            exceptionClass: $exceptionClass,
            message: $message ?? '',
            level: $this->level,
            attributes: $attributes,
            solutions: $this->solutions,
            throwable: $this->throwable,
            applicationPath: $this->applicationPath,
            openFrameIndex: $this->throwable ? null : $stackTrace->firstApplicationFrameIndex(),
            handled: $this->handled,
            events: array_values(array_filter(
                array_map(fn (Span|SpanEvent $span) => $span->toEvent(), $this->events),
            )),
            trackingUuid: $this->trackingUuid ?? self::ids()->uuid(),
        );
    }

    protected function buildStacktrace(): Backtrace
    {
        $stacktrace = $this->throwable
            ? Backtrace::createForThrowable($this->throwable)
            : Backtrace::create();

        return $stacktrace
            ->withArguments($this->withStackTraceArguments)
            ->reduceArguments($this->argumentReducers)
            ->applicationPath($this->applicationPath ?? '');
    }
}
