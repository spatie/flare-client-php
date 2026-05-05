<?php

namespace Spatie\FlareClient\Recorders\ControllerRecorder;

use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\SpansRecorder;
use Spatie\FlareClient\Spans\Span;

class ControllerRecorder extends SpansRecorder
{
    protected bool $recordingController = false;

    protected function configure(array $config): void
    {
        $this->withTraces = true;
        $this->withErrors = false;
    }

    public static function type(): string|RecorderType
    {
        return RecorderType::Controller;
    }

    public function recordStart(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        if ($this->recordingController === true) {
            return null;
        }

        $this->recordingController = true;

        return $this->startSpan(
            'Controller',
            attributes: [
                'flare.span_type' => SpanType::Controller,
                ...$attributes,
            ],
            time: $time
        );
    }

    public function recordEnd(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        if ($this->recordingController === false) {
            return null;
        }

        $this->recordingController = false;

        return $this->endSpan(
            time: $time,
            additionalAttributes: $attributes,
        );
    }
}
