<?php

namespace Spatie\FlareDaemon;

use React\EventLoop\TimerInterface;
use Spatie\FlareDaemon\Contracts\LoopContract;

class CheckForUpdates
{
    private const CHECK_INTERVAL = 60.0;

    private const SHUTDOWN_COUNTDOWN = 300;

    private const COUNTDOWN_LOG_INTERVAL = 60;

    private ?string $currentHash = null;

    private bool $shutdownScheduled = false;

    public function __construct(
        private LoopContract $loop,
        private OutputWriter $output,
        private Ingest $ingest,
        private string $composerLockPath,
    ) {
    }

    public function start(): void
    {
        $this->currentHash = $this->hashFile();

        if ($this->currentHash === null) {
            $this->output->writeLn("Could not read composer.lock at {$this->composerLockPath} — skipping initial hash");
        }

        $this->loop->addPeriodicTimer(self::CHECK_INTERVAL, function (): void {
            $this->check();
        });
    }

    private function check(): void
    {
        if ($this->shutdownScheduled) {
            return;
        }

        $newHash = $this->hashFile();

        if ($newHash === null) {
            return;
        }

        if ($this->currentHash === null) {
            $this->currentHash = $newHash;

            return;
        }

        if ($newHash === $this->currentHash) {
            return;
        }

        $this->output->writeLn("composer.lock changed — initiating graceful shutdown in 5 minutes");
        $this->shutdownScheduled = true;

        $this->scheduleShutdown();
    }

    private function scheduleShutdown(): void
    {
        $remaining = self::SHUTDOWN_COUNTDOWN;

        $this->loop->addPeriodicTimer((float) self::COUNTDOWN_LOG_INTERVAL, function (TimerInterface $timer) use (&$remaining): void {
            $remaining -= self::COUNTDOWN_LOG_INTERVAL;

            if ($remaining <= 0) {
                $this->loop->get()->cancelTimer($timer);
                $this->performShutdown();

                return;
            }

            $minutes = intdiv($remaining, 60);
            $this->output->writeLn("Shutting down in {$minutes} minute(s)...");
        });
    }

    private function performShutdown(): void
    {
        $this->output->writeLn("Shutting down — flushing buffers...");

        $this->ingest->forceDigest()->then(function (): void {
            $this->output->writeLn("All buffers flushed — stopping loop");
            $this->loop->stop();
        });
    }

    private function hashFile(): ?string
    {
        if (! is_file($this->composerLockPath) || ! is_readable($this->composerLockPath)) {
            return null;
        }

        $contents = @file_get_contents($this->composerLockPath);

        if ($contents === false) {
            return null;
        }

        return md5($contents);
    }
}
