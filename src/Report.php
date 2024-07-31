<?php

namespace Spatie\FlareClient;

use ErrorException;
use Spatie\Backtrace\Backtrace;
use Spatie\Backtrace\Frame as SpatieFrame;
use Spatie\ErrorSolutions\Contracts\Solution;
use Spatie\FlareClient\Concerns\HasAttributes;
use Spatie\FlareClient\Concerns\UsesTime;
use Spatie\FlareClient\Solutions\ReportSolution;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\Telemetry;
use Throwable;

class Report
{
    use UsesTime;

    /**
     * @param array<int, Solution> $solutions
     * @param array<int|string, Span> $spans
     * @param array<int|string, SpanEvent> $spanEvents
     */
    public function __construct(
        protected Backtrace $stacktrace,
        protected string $exceptionClass,
        protected string $message,
        protected array $attributes = [],
        protected array $solutions = [],
        protected ?Throwable $throwable = null,
        protected ?string $applicationStage = null,
        protected ?string $applicationPath = null,
        protected ?string $applicationVersion = null,
        protected ?string $languageVersion = null,
        protected ?string $frameworkVersion = null,
        protected ?int $openFrameIndex = null,
        protected ?bool $handled = null,
        protected array $spans = [],
        protected array $spanEvents = [],
        protected ?string $trackingUuid = null,

    ) {
    }

    public function trackingUuid(): string
    {
        return $this->trackingUuid;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'notifier' => $this->notifierName ?? Telemetry::NAME,
            'language' => 'PHP',
            'framework_version' => $this->frameworkVersion,
            'language_version' => $this->languageVersion ?? phpversion(),
            'exception_class' => $this->exceptionClass,
            'seen_at' => $this::getCurrentTime(),
            'message' => $this->message,
            'solutions' => array_map(
                fn (Solution $solution) => ReportSolution::fromSolution($solution)->toArray(),
                $this->solutions,
            ),
            'stacktrace' => $this->stracktraceAsArray(),
            'application_stage' => $this->applicationStage,
            'open_frame_index' => $this->openFrameIndex,
            'application_path' => $this->applicationPath,
            'application_version' => $this->applicationVersion,
            'tracking_uuid' => $this->trackingUuid,
            'handled' => $this->handled,
            'attributes' => $this->attributes,
            'spans' => array_map(fn (Span $span) => $span->toReport(), $this->spans),
            'span_events' => array_map(fn (SpanEvent $spanEvent) => $spanEvent->toReport(), $this->spanEvents),
        ];
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function stracktraceAsArray(): array
    {
        return array_map(
            fn (SpatieFrame $frame) => Frame::fromSpatieFrame($frame)->toArray(),
            $this->cleanupStackTraceForError($this->stacktrace->frames()),
        );
    }

    /**
     * @param array<SpatieFrame> $frames
     *
     * @return array<SpatieFrame>
     */
    protected function cleanupStackTraceForError(array $frames): array
    {
        if ($this->throwable === null || get_class($this->throwable) !== ErrorException::class) {
            return $frames;
        }

        $firstErrorFrameIndex = null;

        $restructuredFrames = array_values(array_slice($frames, 1)); // remove the first frame where error was created

        foreach ($restructuredFrames as $index => $frame) {
            if ($frame->file === $this->throwable->getFile()) {
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
}
