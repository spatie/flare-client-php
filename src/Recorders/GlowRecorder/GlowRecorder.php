<?php

namespace Spatie\FlareClient\Recorders\GlowRecorder;

use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Concerns\RecordsSpanEvents;
use Spatie\FlareClient\Contracts\Recorder;
use Spatie\FlareClient\Contracts\SpanEventsRecorder;
use Spatie\FlareClient\FlareMiddleware\AddGlows;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\Tracer;

class GlowRecorder implements SpanEventsRecorder
{
    /**  @use RecordsSpanEvents<GlowSpanEvent> */
    use RecordsSpanEvents;

    public static function initialize(ContainerInterface $container, array $config): static
    {
        return new self(
            tracer: $container->get(Tracer::class),
            traceGlows: $config['trace_glows'],
            reportGlows: $config['report_glows'],
            maxReportedGlows: $config['max_reported_glows'],
        );
    }

    public function __construct(
        protected Tracer $tracer,
        bool $traceGlows,
        bool $reportGlows,
        ?int $maxReportedGlows,
    ) {
        $this->traceSpanEvents = $traceGlows;
        $this->reportSpanEvents = $reportGlows;
        $this->maxReportedSpanEvents = $maxReportedGlows;
    }

    public function start(): void
    {

    }

    public function record(GlowSpanEvent $glow): void
    {
        $this->persistSpanEvent($glow);
    }
}
