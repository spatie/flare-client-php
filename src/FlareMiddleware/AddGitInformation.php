<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use ReflectionClass;
use Spatie\FlareClient\Report;
use Symfony\Component\Process\Process;

class AddGitInformation implements FlareMiddleware
{
    protected ?string $baseDir = null;

    public function handle(Report $report, Closure $next)
    {
        $this->baseDir = $this->getGitBaseDirectory();

        if (! $this->baseDir) {
            $next($report);
        }

        $report->group('git', [
            'hash' => $this->hash(),
            'message' => $this->message(),
            'tag' => $this->tag(),
            'remote' => $this->remote(),
            'isDirty' => ! $this->isClean(),
        ]);

        return $next($report);
    }

    protected function hash(): ?string
    {
        return $this->command("git log --pretty=format:'%H' -n 1");
    }

    protected function message(): ?string
    {
        return $this->command("git log --pretty=format:'%s' -n 1");
    }

    protected function tag(): ?string
    {
        return $this->command('git describe --tags --abbrev=0');
    }

    protected function remote(): ?string
    {
        return $this->command('git config --get remote.origin.url');
    }

    protected function isClean(): bool
    {
        return empty($this->command('git status -s'));
    }

    protected function getGitBaseDirectory(): ?string
    {
        /** @var Process $process */
        $process = Process::fromShellCommandline("$(git rev-parse --show-toplevel)")->run();

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

    protected function command($command)
    {
        $process = Process::fromShellCommandline($command, $this->baseDir);

        $process->run();

        return trim($process->getOutput());
    }
}
