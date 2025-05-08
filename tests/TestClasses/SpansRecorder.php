<?php

namespace Spatie\FlareClient\Tests\TestClasses;

use Closure;
use Spatie\FlareClient\Concerns\Recorders\RecordsSpans;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Spans\Span;

class SpansRecorder implements \Spatie\FlareClient\Contracts\Recorders\SpansRecorder
{
    use RecordsSpans;

    protected bool $canStartTraces = false;

    protected ?Closure $shouldStartTrace = null;

    protected function configure(array $config): void
    {
        $this->canStartTraces = $config['can_start_traces'] ?? false;
        $this->shouldStartTrace = $config['should_start_trace'] ?? null;

        $this->configureRecorder($config);
    }

    public static function type(): string|RecorderType
    {
        return 'spans';
    }

    protected function canStartTraces(): bool
    {
        return $this->canStartTraces;
    }

    protected function shouldStartTrace(Span $span): bool
    {
        return is_callable($this->shouldStartTrace)
            ? ($this->shouldStartTrace)($span)
            : true;
    }

    public function pushSpan(string $name): ?Span
    {
        return $this->startSpan(name: $name);
    }

    public function popSpan(): ?Span
    {
        return $this->endSpan();
    }

    public function record(
        string $name,
        int $duration,
    ): ?Span {
        return $this->span(
            $name,
            duration: $duration
        );
    }
}
