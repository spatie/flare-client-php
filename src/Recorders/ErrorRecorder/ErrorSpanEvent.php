<?php

namespace Spatie\FlareClient\Recorders\ErrorRecorder;

use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Spans\SpanEvent;
use Throwable;

class ErrorSpanEvent extends SpanEvent
{
    public function __construct(
        public string $message,
        public string $class,
        public ?bool $handled,
        int $timeUs,
        public ?string $id = null,
        public SpanEventType $spanEventType = SpanEventType::Exception,
    ) {
        parent::__construct(
            "Exception - {$this->class}",
            $timeUs,
            $this->collectAttributes(),
        );
    }

    public static function fromReport(array $report, int $timeUs): self
    {
        return new self(
            $report['message'],
            $report['exceptionClass'],
            $report['handled'],
            $timeUs,
            $report['trackingUuid'],
        );
    }

    public static function fromThrowable(Throwable $throwable, int $timeUs): self
    {
        return new self(
            $throwable->getMessage(),
            $throwable::class,
            null,
            $timeUs,
        );
    }

    protected function collectAttributes(): array
    {
        return [
            'flare.span_event_type' => $this->spanEventType,
            'exception.message' => $this->message,
            'exception.type' => $this->class,
            'exception.handled' => $this->handled,
            'exception.id' => $this->id,
        ];
    }
}
