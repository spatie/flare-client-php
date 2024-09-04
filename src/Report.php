<?php

namespace Spatie\FlareClient;

use ErrorException;
use Spatie\Backtrace\Backtrace;
use Spatie\Backtrace\Frame as SpatieFrame;
use Spatie\ErrorSolutions\Contracts\Solution;
use Spatie\FlareClient\Concerns\UsesTime;
use Spatie\FlareClient\Solutions\ReportSolution;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Throwable;

class Report
{
    use UsesTime;

    /**
     * @param array<int, Solution> $solutions
     * @param array<int|string, Span|SpanEvent> $spans
     */
    public function __construct(
        public readonly Backtrace $stacktrace,
        public readonly string $exceptionClass,
        public readonly string $message,
        public readonly array $attributes = [],
        public readonly array $solutions = [],
        public readonly ?Throwable $throwable = null,
        public readonly ?string $applicationPath = null,
        public readonly ?int $openFrameIndex = null,
        public readonly ?bool $handled = null,
        public readonly array $spans = [],
        public readonly ?string $trackingUuid = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'exception_class' => $this->exceptionClass,
            'seen_at' => $this::getCurrentTime(),
            'message' => $this->message,
            'solutions' => array_map(
                fn (Solution $solution) => ReportSolution::fromSolution($solution)->toArray(),
                $this->solutions,
            ),
            'stacktrace' => $this->stracktraceAsArray(),
            'open_frame_index' => $this->openFrameIndex,
            'application_path' => $this->applicationPath,
            'tracking_uuid' => $this->trackingUuid,
            'handled' => $this->handled,
            'attributes' => $this->attributes,
            'spans' => array_map(fn (Span|SpanEvent $span) => $span->toReport(), $this->spans),
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
