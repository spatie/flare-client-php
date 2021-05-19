<?php

namespace Spatie\FlareClient;

use Spatie\Backtrace\Frame as SpatieFrame;

class Frame
{
    protected SpatieFrame $frame;

    public static function fromSpatieFrame(SpatieFrame $frame)
    {
        return new static($frame);
    }

    public function __construct(SpatieFrame $frame)
    {
        $this->frame = $frame;
    }

    public function toArray(): array
    {
        $codeSnippet = $this->frame->getSnippet(30);

        return [
            'line_number' => $this->frame->lineNumber,
            'method' => $this->getFullMethod(),
            'code_snippet' => $codeSnippet,
            'file' => $this->frame->file,
        ];
    }

    protected function getFullMethod(): string
    {
        $method = $this->frame->method;

        if ($class = $this->frame->class->class ?? false) {
            $method = "{$class}::{$method}";
        }

        return $method;
    }
}
