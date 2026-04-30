<?php

namespace Spatie\FlareClient\Recorders\ViewRecorder;

use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\SpansRecorder;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;

class ViewRecorder extends SpansRecorder
{
    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        protected ArgumentReducers|null $argumentReducers,
        array $config
    ) {
        parent::__construct($tracer, $backTracer, $config);
    }

    public static function type(): string|RecorderType
    {
        return RecorderType::View;
    }

    protected function configure(array $config): void
    {
        parent::configure([
            'with_traces' => $config['with_traces'] ?? false,
        ]);
    }

    public function recordRendering(
        string $viewName,
        array $data = [],
        ?string $file = null,
        array $attributes = []
    ): ?Span {
        return $this->startSpan(
            name: "View - {$viewName}",
            attributes: fn () => [
                'flare.span_type' => SpanType::View,
                'view.name' => $viewName,
                'view.file' => $file,
                'view.data' => $data,
                ...$attributes,
            ]
        );
    }

    public function recordRendered(): ?Span
    {
        return $this->endSpan();
    }
}
