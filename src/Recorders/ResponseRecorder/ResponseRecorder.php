<?php

namespace Spatie\FlareClient\Recorders\ResponseRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpans;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\TimeInterval;

class ResponseRecorder implements SpansRecorder
{
    /** @use RecordsSpans<Span> */
    use RecordsSpans;

    protected bool $recordingResponse = false;

    protected function configure(array $config): void
    {
        $this->withTraces = true;
    }

    public static function type(): string|RecorderType
    {
        return RecorderType::Response;
    }

    public function recordStart(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        if ($this->recordingResponse === true) {
            return null;
        }

        $this->recordingResponse = true;

        return $this->startSpan(
            'Response',
            attributes: [
                'flare.span_type' => SpanType::Response,
                ...$attributes,
            ],
            time: $time
        );
    }

    public function recordEnd(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        if ($this->recordingResponse === false) {
            return null;
        }

        $this->recordingResponse = false;

        return $this->endSpan(
            time: $time,
            additionalAttributes: $attributes,
        );
    }

    public function recordResponse(
        array $attributes = [],
        ?int $start = null,
        ?int $end = null,
        ?int $duration = null,
    ): ?Span {
        [$start, $end] = TimeInterval::resolve($this->tracer->time, $start, $end, $duration);

        if ($this->recordStart($attributes, time: $start)) {
            return $this->recordEnd(time: $end);
        }

        return null;
    }
}
