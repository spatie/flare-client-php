<?php

namespace Spatie\FlareClient\AttributesProviders;

use Spatie\FlareClient\FlareMiddleware\AddGitInformation;
use Symfony\Component\Process\Process;
use Throwable;

class GitAttributesProvider
{
    protected array $cached;

    public function __construct(
        protected ?string $applicationPath = null,
    ) {
    }

    public function toArray(bool $useProcess = AddGitInformation::DEFAULT_USE_PROCESS): array
    {
        if (isset($this->cached)) {
            return $this->cached;
        }

        try {
            $baseDirectory = $this->getGitBaseDirectory();

            if (! $baseDirectory) {
                return $this->cached = [];
            }

            return  $this->cached = $useProcess
                ? $this->collectGitProcessInfo($baseDirectory)
                : $this->collectGitFileInfo($baseDirectory);
        } catch (Throwable) {
            return $this->cached = [];
        }
    }

    protected function collectGitFileInfo(string $baseDirectory): array
    {
        $gitDirectory = "{$baseDirectory}/.git";
        $data = [];

        if ($content = @file_get_contents("{$gitDirectory}/HEAD")) {
            $data += $this->collectGitHeadInfo(trim($content), $gitDirectory);
        }

        if ($gitHash = ($data['git.hash'] ?? null)) {
            $data += $this->collectGitCommitInfo($gitDirectory, $gitHash);
        }

        if ($config = file_get_contents($gitDirectory.'/config')) {
            if (preg_match('/\[remote "origin"\][^[]*url\s*=\s*(.+?)$/m', $config, $matches)) {
                $data['git.remote'] = trim($matches[1]);
            }
        }

        return $data;
    }

    protected function collectGitHeadInfo(string $content, string $gitDirectory): array
    {
        $data = [];

        if ($content && strlen($content) === 40) {
            $data['git.hash'] = $content;

            return $data;
        }

        if (! str_starts_with($content, 'ref: refs/heads/')) {
            return $data;
        }

        $data['git.branch'] = substr($content, 16);

        $refId = substr($content, 5);

        if ($hash = @file_get_contents("{$gitDirectory}/{$refId}")) {
            $data['git.hash'] = trim($hash);
        }

        return $data;
    }

    protected function collectGitCommitInfo(string $gitDirectory, string $hash): array
    {
        if (! function_exists('gzuncompress')) {
            return [];
        }

        $objectPath = "{$gitDirectory}/objects/".substr($hash, 0, 2).'/'.substr($hash, 2);

        if (! file_exists($objectPath)) {
            return [];
        }

        $compressed = @file_get_contents($objectPath);

        if (! $compressed) {
            return [];
        }

        $decompressed = gzuncompress($compressed);

        if ($decompressed === false) {
            return [];
        }

        if (preg_match("/\0.*?\n\n([^\n]+)/s", $decompressed, $matches)) {
            return ['git.message' => trim($matches[1])];
        }

        return [];
    }

    protected function collectGitProcessInfo(string $baseDir): array
    {
        $command = <<<'BASH'
git rev-parse HEAD 2>/dev/null || echo "";
echo "---";
git rev-list --format=%s --max-count=1 HEAD 2>/dev/null | tail -n1 || echo "";
echo "---";
git for-each-ref --count=1 --sort=-creatordate --format='%(refname:short)' refs/tags 2>/dev/null || echo "";
echo "---";
git config --get remote.origin.url 2>/dev/null || echo "";
echo "---";
git symbolic-ref --short HEAD 2>/dev/null || echo "";
echo "---";
git diff-index --quiet HEAD 2>/dev/null && echo "clean" || echo "dirty"
BASH;

        $process = Process::fromShellCommandline($command, $baseDir)->setTimeout(1);

        $process->run();

        if (! $process->isSuccessful()) {
            return [];
        }

        $parts = array_map('trim', explode('---', $process->getOutput()));

        if (count($parts) !== 6) {
            return [];
        }

        $data = [
            'git.hash' => $parts[0] ?: null,
            'git.message' => $parts[1] ?: null,
            'git.tag' => $parts[2] ?: null,
            'git.remote' => $parts[3] ?: null,
            'git.branch' => $parts[4] ?: null,
            'git.is_dirty' => $parts[5] === 'dirty',
        ];

        return array_filter($data, fn ($value) => is_bool($value) || ($value !== null && $value !== ''));
    }

    protected function getGitBaseDirectory(): ?string
    {
        if ($this->applicationPath) {
            if (is_dir($this->applicationPath.'/.git')) {
                return $this->applicationPath;
            }

            return null;
        }

        $guessedPath = __DIR__.'/../../';

        if (is_dir($guessedPath.'/.git')) {
            return realpath($guessedPath) ?: null;
        }

        return null;
    }
}
