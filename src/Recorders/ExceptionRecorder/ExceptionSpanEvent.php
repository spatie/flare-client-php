<?php

namespace Spatie\FlareClient\Recorders\ExceptionRecorder;

use Spatie\Backtrace\Frame;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Report;
use Spatie\FlareClient\Spans\SpanEvent;

class ExceptionSpanEvent extends SpanEvent
{
    public function __construct(
        public string $message,
        public string $class,
        public ?bool $handled,
        /** @var array<array{file: string, line: string, method: ?string, class: ?string}> */
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

    public static function fromFlareReport(Report $report): self
    {
        return new self(
            $report->getMessage(),
            $report->getExceptionClass(),
            $report->isHandled(),
            array_map(
                fn (Frame $frame) => [
                    'filename' => $frame->file,
                    'line' => $frame->lineNumber,
                    'class' => $frame->class,
                    'method' => $frame->method,
                ],
                $report->getStacktrace()->frames(),
            ),
            $report->trackingUuid(),
        );
    }

    protected function collectAttributes(): array
    {
        $stackTrace = array_map(
            fn (array $frame) => "{$frame['class']}::{$frame['method']} at {$frame['file']}:{$frame['line']}".PHP_EOL,
            $this->stackTrace,
        );

        return [
            'flare.span_event_type' => $this->spanEventType,
            'exception.message' => $this->message,
            'exception.type' => $this->class,
            'exception.handled' => $this->handled,
            'exception.stacktrace' => $stackTrace,
            'exception.id' => $this->id,
        ];
    }
}
