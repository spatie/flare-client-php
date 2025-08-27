<?php

namespace Spatie\FlareClient;

use ErrorException;
use Exception;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\Backtrace\Backtrace;
use Spatie\Backtrace\Frame;
use Spatie\ErrorSolutions\Contracts\RunnableSolution;
use Spatie\ErrorSolutions\Contracts\Solution;
use Spatie\FlareClient\Concerns\HasAttributes;
use Spatie\FlareClient\Concerns\HasCustomContext;
use Spatie\FlareClient\Contracts\ProvidesFlareContext;
use Spatie\FlareClient\Contracts\WithAttributes;
use Spatie\FlareClient\Enums\OverriddenGrouping;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\Ids;
use Spatie\FlareClient\Support\StacktraceMapper;
use Spatie\FlareClient\Time\Time;
use Throwable;

class ReportFactory implements WithAttributes
{
    use HasAttributes;
    use HasCustomContext;

    protected Resource $resource;

    public ?string $applicationPath = null;

    /** @var ArgumentReducers|null */
    public null|ArgumentReducers $argumentReducers = null;

    public bool $collectStackTraceArguments = true;

    public bool $includeStackTraceWithMessages = false;

    /** @var array<Span|SpanEvent> */
    public array $events = [];

    /** @var array<Solution> */
    public array $solutions = [];

    public ?string $notifierName = null;

    public ?bool $handled = null;

    public ?string $trackingUuid = null;

    /** @var array<class-string, OverriddenGrouping> */
    public array $overriddenGroupings = [];

    protected function __construct(
        public ?Throwable $throwable,
        public string $message,
        public ?string $level,
        public bool $isLog,
    ) {
    }

    public static function createForMessage(string $message, string $logLevel): ReportFactory
    {
        return new self(throwable: null, message: $message, level: $logLevel, isLog: true);
    }

    public static function createForThrowable(
        Throwable $throwable,
    ): ReportFactory {
        $level = null;

        if ($throwable instanceof ErrorException) {
            $level = match ($throwable->getSeverity()) {
                E_ERROR => 'error',
                E_WARNING => 'warning',
                E_PARSE => 'parse',
                E_NOTICE => 'notice',
                E_CORE_ERROR => 'core_error',
                E_CORE_WARNING => 'core_warning',
                E_COMPILE_ERROR => 'compile_error',
                E_COMPILE_WARNING => 'compile_warning',
                E_USER_ERROR => 'user_error',
                E_USER_WARNING => 'user_warning',
                E_USER_NOTICE => 'user_notice',
                E_STRICT => 'strict',
                E_RECOVERABLE_ERROR => 'recoverable_error',
                E_DEPRECATED => 'deprecated',
                E_USER_DEPRECATED => 'user_deprecated',
                default => 'unknown',
            };
        }

        return new self(throwable: $throwable, message: $throwable->getMessage(), level: $level, isLog: false);
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

    public function includeStackTraceWithMessages(bool $includeStackTraceWithMessages = true): self
    {
        $this->includeStackTraceWithMessages = $includeStackTraceWithMessages;

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

    public function collectStackTraceArguments(bool $collectStackTraceArguments): self
    {
        $this->collectStackTraceArguments = $collectStackTraceArguments;

        return $this;
    }

    public function trackingUuid(string $uuid): self
    {
        $this->trackingUuid = $uuid;

        return $this;
    }

    public function overriddenGroupings(array $overriddenGroupings): self
    {
        $this->overriddenGroupings = $overriddenGroupings;

        return $this;
    }

    public function build(
        StacktraceMapper $stacktraceMapper,
        Time $time,
        Ids $ids,
    ): Report {
        if ($this->throwable === null && $this->isLog === false) {
            throw new Exception('No throwable or message provided');
        }

        $stackTrace = $this->buildStacktrace();

        $exceptionClass = $this->throwable
            ? $this->throwable::class
            : "Log";

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

        $attributes['flare.language.name'] = 'PHP';
        $attributes['flare.language.version'] = PHP_VERSION;

        return new Report(
            stacktrace: $stacktraceMapper->map($stackTrace, $this->throwable),
            exceptionClass: $exceptionClass,
            message: $this->message,
            isLog: $this->isLog,
            timeUs: $time->getCurrentTime(),
            level: $this->level,
            attributes: $attributes,
            solutions: $this->mapSolutions(),
            applicationPath: $this->applicationPath,
            openFrameIndex: null,
            handled: $this->handled,
            events: array_values(array_filter(
                array_map(fn (Span|SpanEvent $span) => $span->toEvent(), $this->events),
            )),
            trackingUuid: $this->trackingUuid ?? $ids->uuid(),
            overriddenGrouping: $this->throwable ? $this->overriddenGroupings[$this->throwable::class] ?? null : null,
        );
    }

    /** @return array<Frame> */
    protected function buildStacktrace(): array
    {
        if ($this->isLog && $this->includeStackTraceWithMessages === false) {
            return [new Frame(
                file: 'Log',
                lineNumber: 0,
                arguments: null,
                method: 'Stacktrace disabled',
            )];
        }

        $stacktrace = $this->isLog
            ? Backtrace::create()
            : Backtrace::createForThrowable($this->throwable);

        $frames = $stacktrace
            ->withArguments($this->collectStackTraceArguments)
            ->reduceArguments($this->argumentReducers)
            ->applicationPath($this->applicationPath ?? '')
            ->frames();

        if (! $this->isLog) {
            return $frames;
        }

        $firstApplicationFrameIndex = null;

        foreach ($frames as $index => $frame) {
            if ($frame->applicationFrame) {
                $firstApplicationFrameIndex = $index;

                break;
            }
        }

        if ($firstApplicationFrameIndex === null) {
            return $frames;
        }

        return array_values(array_slice($frames, $firstApplicationFrameIndex));
    }

    protected function mapSolutions(): array
    {
        return array_map(
            function (Solution $solution) {
                $isRunnable = $solution instanceof RunnableSolution;

                return [
                    'class' => get_class($solution),
                    'title' => $solution->getSolutionTitle(),
                    'description' => $solution->getSolutionDescription(),
                    'links' => $solution->getDocumentationLinks(),
                    'actionDescription' => $isRunnable ? $solution->getSolutionActionDescription() : null,
                    'isRunnable' => $isRunnable,
                    'aiGenerated' => $solution->aiGenerated ?? false,
                ];
            },
            $this->solutions
        );
    }
}
