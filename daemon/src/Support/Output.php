<?php

namespace Spatie\FlareDaemon\Support;

use Stringable;

class Output
{
    /** @var resource|null */
    protected $stdout;

    /** @var resource|null */
    protected $stderr;

    /**
     * @param resource|null $stdout
     * @param resource|null $stderr
     */
    public function __construct($stdout = null, $stderr = null, protected bool $verbose = false)
    {
        $resolvedStdout = $stdout ?? fopen('php://stdout', 'ab');
        $resolvedStderr = $stderr ?? fopen('php://stderr', 'ab');

        $this->stdout = is_resource($resolvedStdout) ? $resolvedStdout : null;
        $this->stderr = is_resource($resolvedStderr) ? $resolvedStderr : null;
    }

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        if (! $this->verbose) {
            return;
        }

        $this->write($this->stdout, 'DEBUG', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->write($this->stdout, 'INFO', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->write($this->stdout, 'WARNING', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->write($this->stderr, 'ERROR', $message, $context);
    }

    /**
     * @param resource|null $stream
     * @param array<string, mixed> $context
     */
    protected function write($stream, string $level, string $message, array $context = []): void
    {
        if (! is_resource($stream)) {
            return;
        }

        $suffix = $context === []
            ? ''
            : ' '.Json::encode($this->normalize($context));

        fwrite(
            $stream,
            sprintf("[%s] %s %s%s\n", gmdate('c'), $level, $message, $suffix),
        );
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    protected function normalize(array $context): array
    {
        $normalized = [];

        foreach ($context as $key => $value) {
            $normalized[$key] = match (true) {
                $value instanceof \Throwable => [
                    'class' => $value::class,
                    'message' => $value->getMessage(),
                ],
                is_scalar($value), $value === null => $value,
                $value instanceof Stringable => (string) $value,
                is_array($value) => $this->normalize($value),
                default => get_debug_type($value),
            };
        }

        return $normalized;
    }
}
