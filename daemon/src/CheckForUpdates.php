<?php

namespace Spatie\FlareDaemon;

use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use Spatie\FlareDaemon\Support\Output;

class CheckForUpdates
{
    protected ?string $currentHash = null;

    protected ?TimerInterface $timer = null;

    public function __construct(
        protected LoopInterface $loop,
        protected string $composerLockPath,
        protected Output $output,
        protected \Closure $onChange,
        protected float $intervalSeconds = 60.0,
    ) {
        $this->currentHash = $this->hash();
    }

    public function start(): void
    {
        if ($this->timer !== null) {
            return;
        }

        $this->timer = $this->loop->addPeriodicTimer($this->intervalSeconds, fn () => $this->check());
    }

    public function stop(): void
    {
        if ($this->timer === null) {
            return;
        }

        $this->loop->cancelTimer($this->timer);
        $this->timer = null;
    }

    public function check(): void
    {
        $hash = $this->hash();

        if ($hash === null) {
            return;
        }

        if ($this->currentHash === null) {
            $this->currentHash = $hash;

            return;
        }

        if ($hash === $this->currentHash) {
            return;
        }

        $this->output->info('composer.lock change detected', [
            'path' => $this->composerLockPath,
        ]);

        $this->stop();
        ($this->onChange)();
    }

    protected function hash(): ?string
    {
        return is_file($this->composerLockPath) && is_readable($this->composerLockPath)
            ? hash_file('sha256', $this->composerLockPath) ?: null
            : null;
    }
}
