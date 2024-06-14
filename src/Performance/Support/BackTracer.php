<?php

namespace Spatie\FlareClient\Performance\Support;

use Spatie\Backtrace\Backtrace;
use Spatie\Backtrace\Frame;

class BackTracer
{
    public function frames(int $limit = null): array
    {
        $backTracer = Backtrace::create()
            ->applicationPath(realpath(base_path()))
            ->offset(1);

        if ($limit) {
            $backTracer->limit($limit);
        }

        return $backTracer->frames();
    }

    public function firstApplicationFrame(int $limit = null): ?Frame
    {
        // TODO: backtrace package seems broken and marks all frames as application frames
        foreach ($this->frames($limit) as $frame) {
            if ($frame->applicationFrame) {
                return $frame;
            }
        }

        return null;
    }

    public function getOriginAttributes(): ?array
    {
        $frame = $this->firstApplicationFrame(25);

        if(! $frame) {
            return null;
        }

        $function = match (true){
            $frame->class && $frame->method => "{$frame->class}::{$frame->method}",
            $frame->method => $frame->method,
            $frame->class => $frame->class,
            default => 'unknown',
        };

        return [
            'code.filepath' => $frame->file,
            'code.lineno' => $frame->lineNumber,
            'code.function' => $function,
        ];
    }
}
