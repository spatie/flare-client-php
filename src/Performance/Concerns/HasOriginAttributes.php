<?php

namespace Spatie\FlareClient\Performance\Concerns;

use Spatie\Backtrace\Frame;

/** @mixin HasAttributes */
trait HasOriginAttributes
{
    public function setOriginFrame(Frame $frame): void
    {
        $function = match (true) {
            $frame->class && $frame->method => "{$frame->class}::{$frame->method}",
            $frame->method => $frame->method,
            $frame->class => $frame->class,
            default => 'unknown',
        };

        $this->addAttributes([
            'code.filepath' => $frame->file,
            'code.lineno' => $frame->lineNumber,
            'code.function' => $function,
        ]);
    }
}
