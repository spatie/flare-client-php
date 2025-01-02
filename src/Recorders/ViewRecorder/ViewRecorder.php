<?php

namespace Spatie\FlareClient\Recorders\ViewRecorder;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\FlareClient\Concerns\Recorders\RecordsPendingSpans;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;

class ViewRecorder implements SpansRecorder
{
    /** @use RecordsPendingSpans<Span> */
    use RecordsPendingSpans;

    public function __construct(
        protected Tracer $tracer,
        protected BackTracer $backTracer,
        protected ArgumentReducers|null $argumentReducers,
        array $config
    ) {
        $this->configure([
            'trace' => $config['trace'] ?? false,
        ]);
    }

    public static function register(ContainerInterface $container, array $config): Closure
    {
        return fn () => new self(
            $container->get(Tracer::class),
            $container->get(BackTracer::class),
            $container->get(ArgumentReducers::class),
            $config,
        );
    }

    public static function type(): string|RecorderType
    {
        return RecorderType::View;
    }

    public function recordRendering(
        string $viewName,
        array $data = [],
        ?string $file = null,
        array $attributes = []
    ): ?Span {
        return $this->startSpan(fn () => Span::build(
            $this->tracer->currentTraceId(),
            $this->tracer->currentSpanId(),
            "View - {$viewName}",
            attributes: [
                'flare.span_type' => SpanType::View,
                'view.name' => $viewName,
                'view.file' => $file,
                ...$attributes,
            ]
        ));
    }

    public function recordRendered(): ?Span
    {
        return $this->endSpan();
    }
}
