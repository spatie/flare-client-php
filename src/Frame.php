<?php

namespace Spatie\FlareClient;

use Spatie\Backtrace\Frame as SpatieFrame;

class Frame
{
    public static function fromSpatieFrame(
        SpatieFrame $frame,
        ?array $reducedArguments = null,
    ): self {
        return new self($frame, $reducedArguments);
    }

    public function __construct(
        protected SpatieFrame $frame,
        protected ?array $reducedArguments = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'file' => $this->frame->file,
            'line_number' => $this->frame->lineNumber,
            'method' => $this->frame->method,
            'class' => $this->frame->class,
            'code_snippet' => $this->frame->getSnippet(30),
            'arguments' => $this->reducedArguments,
            'application_frame' => $this->frame->applicationFrame,
        ];
    }
}
