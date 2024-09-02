<?php

namespace Spatie\FlareClient;

use Exception;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\Backtrace\Backtrace;
use Spatie\ErrorSolutions\Contracts\Solution;
use Spatie\FlareClient\Concerns\UsesIds;
use Spatie\FlareClient\Concerns\HasAttributes;
use Spatie\FlareClient\Concerns\HasUserProvidedContext;
use Spatie\FlareClient\Contracts\ProvidesFlareContext;
use Spatie\FlareClient\Contracts\WithAttributes;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\LaravelFlare\Exceptions\ViewException;
use Spatie\LaravelIgnition\Exceptions\ViewException as IgnitionViewException;
use Throwable;

class ReportFactory implements WithAttributes
{
    use HasAttributes;
    use HasUserProvidedContext;
    use UsesIds;

    protected Resource $resource;

    public ?string $applicationPath = null;

    public ?string $version = null;

    /** @var ArgumentReducers|null */
    public null|ArgumentReducers $argumentReducers = null;

    public bool $withStackTraceArguments = true;

    public ?string $applicationStage = null;

    /** @var array<Span> */
    public array $spans = [];

    /** @var array<SpanEvent> */
    public array $spanEvents = [];

    /** @var array<Solution> */
    public array $solutions = [];

    public ?string $notifierName = null;

    public ?bool $handled = null;

    public ?string $applicationVersion = null;

    public ?string $languageVersion = null;

    public ?string $frameworkVersion = null;

    public ?string $trackingUuid = null;

    protected function __construct(
        public ?Throwable $throwable = null,
        public ?string $message = null,
        public ?string $logLevel = null,
    ) {
    }

    public static function createForMessage(string $message, string $logLevel): ReportFactory
    {
        return new self(message: $message, logLevel: $logLevel);
    }

    public static function createForThrowable(
        Throwable $throwable,
    ): ReportFactory {
        return new self(throwable: $throwable);
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
        array_push($this->spans, ...$span);

        return $this;
    }

    public function spanEvent(SpanEvent ...$spanEvent): self
    {
        array_push($this->spanEvents, ...$spanEvent);

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

    public function languageVersion(string $languageVersion): self
    {
        $this->languageVersion = $languageVersion;

        return $this;
    }

    public function frameworkVersion(string $frameworkVersion): self
    {
        $this->frameworkVersion = $frameworkVersion;

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
        if ($this->throwable === null && ($this->message === null || $this->logLevel === null)) {
            throw new Exception('No throwable or message provided');
        }

        $stackTrace = $this->buildStacktrace();

        $exceptionClass = $this->throwable
            ? $this->getClassForThrowable($this->throwable)
            : $this->logLevel;

        $exceptionContext = $this->throwable instanceof ProvidesFlareContext
            ? $this->throwable->context()
            : [];

        $message = $this->throwable
            ? $this->throwable->getMessage()
            : $this->message;

        $attributes = $this->attributes;

        if(! empty($this->userProvidedContext) || ! empty($exceptionContext)) {
            $attributes['context.user'] = array_merge_recursive_distinct(
                $this->userProvidedContext,
                $exceptionContext,
            );
        }

        return new Report(
            stacktrace: $stackTrace,
            exceptionClass: $exceptionClass,
            message: $message,
            resource: $this->resource,
            attributes: $attributes,
            solutions: $this->solutions,
            throwable: $this->throwable,
            applicationPath: $this->applicationPath,
            languageVersion: $this->languageVersion,
            frameworkVersion: $this->frameworkVersion,
            openFrameIndex: $this->throwable ? null : $stackTrace->firstApplicationFrameIndex(),
            handled: $this->handled,
            spans: $this->spans,
            spanEvents: $this->spanEvents,
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
            ->applicationPath($applicationPath ?? '');
    }

    protected function getClassForThrowable(Throwable $throwable): string
    {
        // TODO: move to Laravel Client
        /** @phpstan-ignore-next-line */
        if ($throwable::class === IgnitionViewException::class || $throwable::class === ViewException::class) {
            /** @phpstan-ignore-next-line */
            if ($previous = $throwable->getPrevious()) {
                return get_class($previous);
            }
        }

        return get_class($throwable);
    }
}
