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
use Spatie\FlareClient\Contracts\ProvidesFlareContext;
use Spatie\FlareClient\Contracts\WithAttributes;
use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\Enums\OverriddenGrouping;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\Ids;
use Spatie\FlareClient\Time\Time;
use Throwable;

class ReportFactory implements WithAttributes
{
    use HasAttributes;

    public ?string $message = null;

    /** @var array<Span|SpanEvent> */
    public array $events = [];

    /** @var array<Solution> */
    public array $solutions = [];

    public ?bool $handled = null;

    public ?string $trackingUuid = null;

    public Throwable $throwable;

    public ?string $level = null;

    /** @var array<string, mixed> */
    public array $customContext = [];

    /**
     * @param array<class-string, OverriddenGrouping> $overriddenGroupings
     *
     */
    public function __construct(
        protected Time $time,
        protected Ids $ids,
        public Resource $resource,
        public null|ArgumentReducers $argumentReducers,
        public bool $collectStackTraceArguments,
        public array $overriddenGroupings,
        public ?string $applicationPath,
    ) {
    }

    public function new(): self
    {
        $clone = clone $this;

        $clone->attributes = [];
        $clone->message = null;
        $clone->events = [];
        $clone->solutions = [];
        $clone->handled = null;
        $clone->trackingUuid = null;
        $clone->level = null;
        unset($clone->throwable);

        return $clone;
    }

    public function throwable(
        Throwable $throwable,
    ): self {
        $this->throwable = $throwable;

        if ($throwable instanceof ErrorException) {
            $this->level = match ($throwable->getSeverity()) {
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

        $this->message = $throwable->getMessage();

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

    public function solution(Solution ...$solution): self
    {
        array_push($this->solutions, ...$solution);

        return $this;
    }

    public function trackingUuid(string $uuid): self
    {
        $this->trackingUuid = $uuid;

        return $this;
    }

    public function context(string|array $key, mixed $value = null): self
    {
        if (is_array($key)) {
            $this->customContext = array_merge($this->customContext, $key);

            return $this;
        }

        $this->customContext[$key] = $value;

        return $this;
    }

    public function toArray(): array
    {
        if (! isset($this->throwable)) {
            throw new Exception('No throwable provided');
        }

        $attributes = array_merge(
            isset($this->resource) ? $this->resource->export(FlareEntityType::Errors) : [],
            $this->attributes,
        );

        if ($this->throwable instanceof ProvidesFlareContext) {
            $attributes['context.exception'] = array_merge(
                $attributes['context.exception'] ?? [],
                $this->throwable->context()
            );
        }

        if (! empty($this->customContext)) {
            $attributes['context.custom'] = array_merge(
                $attributes['context.custom'] ?? [],
                $this->customContext
            );
        }

        $attributes['flare.language.name'] = 'PHP';
        $attributes['flare.language.version'] = PHP_VERSION;

        $stackTrace = $this->cleanupStackTraceForError(
            $this->buildStacktrace(),
            $this->throwable,
        );

        $report = [
            'exceptionClass' => $this->throwable::class,
            'seenAtUnixNano' => $this->time->getCurrentTime(),
            'message' => $this->message,
            'solutions' => $this->mapSolutions(),
            'stacktrace' => $this->mapStackTrace($stackTrace),
            'previous' => $this->buildPrevious(),
            'openFrameIndex' => null,
            'applicationPath' => $this->applicationPath,
            'trackingUuid' => $this->trackingUuid ?? $this->ids->uuid(),
            'handled' => $this->handled,
            'attributes' => $attributes,
            'code' => $this->throwable->getCode(),
            'events' => array_values(array_filter(
                array_map(fn (Span|SpanEvent $span) => $span->toEvent(), $this->events),
            )),
            'isLog' => false,
            'overriddenGrouping' => $this->overriddenGroupings[$this->throwable::class] ?? null,
        ];

        if ($this->level !== null) {
            $report['level'] = $this->level;
        }

        return $report;
    }

    protected function buildPrevious(): array
    {
        $previous = [];

        $current = $this->throwable;

        while ($previousThrowable = $current->getPrevious()) {
            $stackTrace = $this->cleanupStackTraceForError(
                $this->buildStacktrace(),
                $this->throwable,
            );

            $previous[] = [
                'exceptionClass' => $previousThrowable::class,
                'message' => $previousThrowable->getMessage(),
                'stacktrace' => $this->mapStackTrace($stackTrace),
            ];

            $current = $previousThrowable;
        }

        return $previous;
    }

    /** @return array<Frame> */
    protected function buildStacktrace(): array
    {
        $frames = Backtrace::createForThrowable($this->throwable)
            ->withArguments($this->collectStackTraceArguments)
            ->reduceArguments($this->argumentReducers)
            ->applicationPath($this->applicationPath ?? '')
            ->frames();

        $firstApplicationFrameIndex = null;

        foreach ($frames as $index => $frame) {
            if ($frame->applicationFrame) {
                $firstApplicationFrameIndex = (int) $index;

                break;
            }
        }

        if ($firstApplicationFrameIndex === null) {
            return $frames;
        }

        return array_values(array_slice($frames, $firstApplicationFrameIndex));
    }

    protected function cleanupStackTraceForError(
        array $frames,
        Throwable $throwable,
    ): array {
        if ($throwable::class !== ErrorException::class) {
            return $frames;
        }

        $firstErrorFrameIndex = null;

        $restructuredFrames = array_values(array_slice($frames, 1)); // remove the first frame where error was created

        foreach ($restructuredFrames as $index => $frame) {
            if ($frame->file === $throwable->getFile()) {
                $firstErrorFrameIndex = $index;

                break;
            }
        }

        if ($firstErrorFrameIndex === null) {
            return $frames;
        }

        $restructuredFrames[$firstErrorFrameIndex]->arguments = null; // Remove error arguments

        return array_values(array_slice($restructuredFrames, $firstErrorFrameIndex));
    }

    /** @param array<Frame> $frames */
    protected function mapStackTrace(array $frames): array
    {
        return array_map(
            fn (Frame $frame) => [
                'file' => $frame->file,
                'lineNumber' => $frame->lineNumber,
                'method' => $frame->method,
                'class' => $frame->class,
                'codeSnippet' => $frame->getSnippet(30),
                'arguments' => $frame->arguments,
                'isApplicationFrame' => $frame->applicationFrame,
            ],
            $frames
        );
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
