<?php

namespace Spatie\FlareClient\Tests\TestClasses;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpans;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Spans\Span;

class SpansRecorder implements \Spatie\FlareClient\Contracts\Recorders\SpansRecorder
{
    use RecordsSpans;

    protected bool $canStartTraces = false;

    protected function configure(array $config): void
    {
        $this->canStartTraces = $config['can_start_traces'] ?? false;

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

    public function pushSpan(string $name): ?Span
    {
        return $this->startSpan(fn () => Span::build(
            traceId: $this->tracer->currentTraceId() ?? '',
            parentId: $this->tracer->currentSpanId(),
            name: $name,
        ));
    }

    public function popSpan(): ?Span
    {
        return $this->endSpan();
    }
}
