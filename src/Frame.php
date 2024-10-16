<?php

namespace Spatie\FlareClient;

use Spatie\Backtrace\Frame as SpatieFrame;

class Frame
{
    public static function fromSpatieFrame(
        SpatieFrame $frame,
    ): self {
        return new self($frame);
    }

    public function __construct(
        protected SpatieFrame $frame,
    ) {
    }

    public function toArray(): array
    {
        return [
            'file' => $this->frame->file,
            'lineNumber' => $this->frame->lineNumber,
            'method' => $this->frame->method,
            'class' => $this->frame->class,
            'codeSnippet' => $this->frame->getSnippet(30),
            'arguments' => $this->frame->arguments,
            'isApplicationFrame' => $this->frame->applicationFrame,
        ];
    }
}
