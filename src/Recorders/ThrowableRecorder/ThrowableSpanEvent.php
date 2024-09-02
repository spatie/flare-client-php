<?php

namespace Spatie\FlareClient\Recorders\ThrowableRecorder;

use Spatie\Backtrace\Frame;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Report;
use Spatie\FlareClient\Spans\SpanEvent;
use Throwable;

class ThrowableSpanEvent extends SpanEvent
{
    public function __construct(
        public string $message,
        public string $class,
        public ?bool $handled,
        /** @var string[] */
        public array $stackTrace,
        public ?string $id = null,
        ?int $timeUs = null,
        public SpanEventType $spanEventType = SpanEventType::Exception,
    ) {
        parent::__construct(
            "Exception - {$this->class}",
            $timeUs ?? static::getCurrentTime(),
            $this->collectAttributes(),
        );
    }

    public static function fromReport(Report $report): self
    {
        $stackTrace = array_map(
            fn (Frame $frame) => "{$frame->class}::{$frame->method} at {$frame->file}:{$frame->lineNumber}".PHP_EOL,
            $report->stacktrace->frames(),
        );

        return new self(
            $report->message,
            $report->exceptionClass,
            $report->handled ?? false,
            $stackTrace,
            $report->trackingUuid
        );
    }

    public static function fromThrowable(Throwable $throwable): self
    {
        return new self(
            $throwable->getMessage(),
            $throwable::class,
            null,
            array_map(
                fn (Frame $frame) => "{$frame->class}::{$frame->method} at {$frame->file}:{$frame->lineNumber}".PHP_EOL,
                $throwable->getTrace(),
            ),
        );
    }

    protected function collectAttributes(): array
    {
        return [
            'flare.span_event_type' => $this->spanEventType,
            'exception.message' => $this->message,
            'exception.type' => $this->class,
            'exception.handled' => $this->handled,
            'exception.stacktrace' => $this->stackTrace,
            'exception.id' => $this->id,
        ];
    }
}
