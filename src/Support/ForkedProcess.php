<?php

namespace Spatie\FlareClient\Support;

use Closure;

class ForkedProcess
{
    protected readonly bool $canFork;

    protected int|false $processId;

    /** @var array<int, Closure(): void> */
    protected array $callbacks = [];

    public function __construct()
    {
        $this->canFork = function_exists('pcntl_fork');
        $this->processId = getmypid();
    }

    /** @param Closure(): void $callback */
    public function onFork(Closure $callback): void
    {
        $this->callbacks[] = $callback;
    }

    public function detect(): void
    {
        if ($this->canFork === false) {
            return;
        }

        if ($this->processId === getmypid()) {
            return;
        }

        $this->processId = getmypid();

        foreach ($this->callbacks as $callback) {
            $callback();
        }
    }
}
