<?php

namespace Spatie\FlareClient;

use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Enums\OverriddenGrouping;
use Spatie\FlareClient\Support\ReportSanitizer;

class Report
{
    /**
     * @param array<int, array{file: string, lineNumber: int, method: string|null, class: string|null, codeSnippet: array<string>, arguments: array|null, isApplicationFrame: bool}> $stacktrace
     * @param array<int, array{class: string, title: string, description: string, links: string[], actionDescription: string|null, isRunnable: bool, aiGenerated: bool}> $solutions
     * @param array<int|string, array{type: FlareSpanType|FlareSpanEventType, startTimeUnixNano: int, endTimeUnixNano: int|null, attributes: array}> $events
     */
    public function __construct(
        public readonly array $stacktrace,
        public readonly string $exceptionClass,
        public readonly string $message,
        public readonly bool $isLog,
        public readonly int $timeUs,
        public readonly ?string $level = null,
        public readonly array $attributes = [],
        public readonly array $solutions = [],
        public readonly ?string $applicationPath = null,
        public readonly ?int $openFrameIndex = null,
        public readonly ?bool $handled = null,
        public readonly array $events = [],
        public readonly ?string $trackingUuid = null,
        public readonly ?OverriddenGrouping $overriddenGrouping = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $report = [
            'exceptionClass' => $this->exceptionClass,
            'seenAtUnixNano' => $this->timeUs,
            'message' => $this->message,
            'solutions' => $this->solutions,
            'stacktrace' => $this->stacktrace,
            'openFrameIndex' => $this->openFrameIndex,
            'applicationPath' => $this->applicationPath,
            'trackingUuid' => $this->trackingUuid,
            'handled' => $this->handled,
            'attributes' => $this->attributes,
            'events' => $this->events,
            'isLog' => $this->isLog,
            'overriddenGrouping' => $this->overriddenGrouping?->value,
        ];

        if ($this->level !== null) {
            $report['level'] = $this->level;
        }

        return ReportSanitizer::sanitizePayload($report);
    }
}
