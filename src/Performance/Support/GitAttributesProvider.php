<?php

namespace Spatie\FlareClient\Performance\Support;

use Symfony\Component\Process\Process;
use Throwable;

class GitAttributesProvider
{
    // TODO: code used from ignition, let's use it again over there
    public function getAttributes(): ?array
    {
        try {
            $baseDir = $this->getGitBaseDirectory();

            if (! $baseDir) {
                return null;
            }

            return [
                'git.hash' => $this->hash($baseDir),
                'git.message' => $this->message($baseDir),
                'git.tag' => $this->tag($baseDir),
                'git.remote' => $this->remote($baseDir),
                'git.is_dirty' => ! $this->isClean($baseDir),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    protected function hash(string $basedir): ?string
    {
        return $this->command($basedir, "git log --pretty=format:'%H' -n 1") ?: null;
    }

    protected function message(string $basedir): ?string
    {
        return $this->command($basedir, "git log --pretty=format:'%s' -n 1") ?: null;
    }

    protected function tag(string $basedir): ?string
    {
        return $this->command($basedir, 'git describe --tags --abbrev=0') ?: null;
    }

    protected function remote(string $basedir): ?string
    {
        return $this->command($basedir, 'git config --get remote.origin.url') ?: null;
    }

    protected function isClean(string $basedir): bool
    {
        return empty($this->command($basedir, 'git status -s'));
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

    protected function command(string $basedir, string $command): string
    {
        $process = Process::fromShellCommandline($command, $basedir)->setTimeout(1);

        $process->run();

        return trim($process->getOutput());
    }
}
