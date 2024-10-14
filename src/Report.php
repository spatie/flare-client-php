<?php

namespace Spatie\FlareClient;

use ErrorException;
use Spatie\Backtrace\Backtrace;
use Spatie\Backtrace\Frame as SpatieFrame;
use Spatie\ErrorSolutions\Contracts\Solution;
use Spatie\FlareClient\Concerns\UsesTime;
use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Solutions\ReportSolution;
use Throwable;

class Report
{
    use UsesTime;

    /**
     * @param array<int, Solution> $solutions
     * @param array<int|string, array{type: FlareSpanType|FlareSpanEventType, startTimeUnixNano: int, endTimeUnixNano: int|null, attributes: array}> $events
     */
    public function __construct(
        public readonly Backtrace $stacktrace,
        public readonly string $exceptionClass,
        public readonly string $message,
        public readonly bool $isLog,
        public readonly ?string $level = null,
        public readonly array $attributes = [],
        public readonly array $solutions = [],
        public readonly ?Throwable $throwable = null,
        public readonly ?string $applicationPath = null,
        public readonly ?int $openFrameIndex = null,
        public readonly ?bool $handled = null,
        public readonly array $events = [],
        public readonly ?string $trackingUuid = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $report = [
            'exceptionClass' => $this->exceptionClass,
            'seenAtUnixNano' => $this::getCurrentTime(),
            'message' => $this->message,
            'solutions' => array_map(
                fn (Solution $solution) => ReportSolution::fromSolution($solution)->toArray(),
                $this->solutions,
            ),
            'stacktrace' => $this->stracktraceAsArray(),
            'openFrameIndex' => $this->openFrameIndex,
            'applicationPath' => $this->applicationPath,
            'trackingUuid' => $this->trackingUuid,
            'handled' => $this->handled,
            'attributes' => $this->attributes,
            'events' => $this->events,
            'isLog' => $this->isLog,
        ];

        if ($this->level !== null) {
            $report['level'] = $this->level;
        }

        return $report;
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
