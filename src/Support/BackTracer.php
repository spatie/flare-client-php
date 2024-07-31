<?php

namespace Spatie\FlareClient\Support;

use Closure;
use Spatie\Backtrace\Backtrace;
use Spatie\Backtrace\Frame;

class BackTracer
{
    public function __construct(
        protected ?string $applicationPath,
    )
    {
    }

    public function frames(int $limit = null): array
    {
        $backTracer = Backtrace::create()
            ->offset(1);

        if($this->applicationPath){
            // TODO: make it clear in docs this is required to correctly backtrace
            $backTracer->applicationPath($this->applicationPath);
        }

        if ($limit) {
            $backTracer->limit($limit);
        }

        return $backTracer->frames();
    }

    public function firstApplicationFrame(?int $limit = null): ?Frame
    {
        // When the backtrace package is symlinked, this code isn't working as it should
        foreach ($this->frames($limit) as $frame) {
            if ($frame->applicationFrame) {
                return $frame;
            }
        }

        return null;
    }

    /**
     * @param \Closure(Frame): bool $closure
     */
    public function after(Closure $closure, ?int $limit = null): ?Frame
    {
        for ($i = 0; $i < count($this->frames($limit)); $i++) {
            $frame = $this->frames($limit)[$i];

            if ($closure($frame)) {
                return $this->frames($limit)[$i + 1] ?? null;
            }
        }

        return null;
    }
}
