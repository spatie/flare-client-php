<?php

namespace Spatie\FlareClient\Tests\TestClasses;

use Exception;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Concerns\RecordsSpanEvents;
use Spatie\FlareClient\Concerns\RecordsSpans;
use Spatie\FlareClient\Concerns\UsesTime;
use Spatie\FlareClient\Contracts\SpansRecorder as BaseSpansRecorder;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Tracer;

class SpansRecorder implements BaseSpansRecorder
{
    use RecordsSpans;

    public static function initialize(ContainerInterface $container, array $config): static
    {
        throw new Exception('We do not test this');
    }

    public function __construct(
        protected Tracer $tracer,
        bool $traceSpans = true,
        bool $reportSpans = true,
        ?int $maxReportedSpans = null,
    ) {
        $this->traceSpans = $traceSpans;
        $this->reportSpans = $reportSpans;
        $this->maxReportedSpans = $maxReportedSpans;
    }

    public function start(): void
    {
    }

    public function record(string $message): void
    {
        $isSampling = $this->tracer->isSamping();

        $span = Span::build(
            traceId: $isSampling ? $this->tracer->currentTraceId() : '',
            name: "Span - {$message}",
            attributes: [
                'message' => $message,
            ],
        );

        $this->persistSpan($span);
    }
}
