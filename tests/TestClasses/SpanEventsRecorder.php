<?php

namespace Spatie\FlareClient\Tests\TestClasses;

use Exception;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Concerns\RecordsSpanEvents;
use Spatie\FlareClient\Concerns\UsesTime;
use Spatie\FlareClient\Contracts\SpanEventsRecorder as BaseSpanEventsRecorder;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowSpanEvent;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Tracer;

class SpanEventsRecorder implements BaseSpanEventsRecorder
{
    use RecordsSpanEvents;

    public static function initialize(ContainerInterface $container, array $config): static
    {
        throw new Exception('We do not test this');
    }

    public function __construct(
        protected Tracer $tracer,
        bool $traceSpanEvents = true,
        bool $reportSpanEvents = true,
        ?int $maxReportedSpanEvents = null,
    ) {
        $this->traceSpanEvents = $traceSpanEvents;
        $this->reportSpanEvents = $reportSpanEvents;
        $this->maxReportedSpanEvents = $maxReportedSpanEvents;
    }

    public function start(): void
    {
    }

    public function record(string $message): void
    {
        $spanEvent = SpanEvent::build(
            name: "Span Event - {$message}",
            attributes: [
                'message' => $message,
            ],
        );

        $this->persistSpanEvent($spanEvent);
    }
}
