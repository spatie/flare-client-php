<?php

namespace Spatie\FlareClient\AttributesProviders;

use Symfony\Component\Process\Process;
use Throwable;

class GitAttributesProvider
{
    protected static array $cached;

    public function toArray(): array
    {
        if (isset(static::$cached)) {
            return static::$cached;
        }

        try {
            $baseDir = $this->getGitBaseDirectory();

            if (! $baseDir) {
                return static::$cached = [];
            }

            return array_filter([
                'git.hash' => $this->hash($baseDir),
                'git.message' => $this->message($baseDir),
                'git.tag' => $this->tag($baseDir),
                'git.remote' => $this->remote($baseDir),
                'git.is_dirty' => ! $this->isClean($baseDir),
            ]);
        } catch (Throwable) {
        }

        return static::$cached = [];
    }

    protected function hash(string $baseDir): ?string
    {
        return $this->command($baseDir, "git log --pretty=format:'%H' -n 1") ?: null;
    }

    protected function message(string $baseDir): ?string
    {
        return $this->command($baseDir, "git log --pretty=format:'%s' -n 1") ?: null;
    }

    protected function tag(string $baseDir): ?string
    {
        return $this->command($baseDir, 'git describe --tags --abbrev=0') ?: null;
    }

    protected function remote(string $baseDir): ?string
    {
        return $this->command($baseDir, 'git config --get remote.origin.url') ?: null;
    }

    protected function isClean(string $baseDir): bool
    {
        return empty($this->command($baseDir, 'git status -s'));
    }

    protected function getGitBaseDirectory(): ?string
    {
        $process = Process::fromShellCommandline("echo $(git rev-parse --show-toplevel)")->setTimeout(1);

        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $directory = trim($process->getOutput());

        if (! file_exists($directory)) {
            return null;
        }

        return $directory;
    }

    protected function command(string $baseDir, string $command): string
    {
        $process = Process::fromShellCommandline($command, $baseDir)->setTimeout(1);

        $process->run();

        return trim($process->getOutput());
    }
}
