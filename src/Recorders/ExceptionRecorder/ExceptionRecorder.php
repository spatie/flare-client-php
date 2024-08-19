<?php

namespace Spatie\FlareClient\Recorders\ExceptionRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpanEvents;
use Spatie\FlareClient\Contracts\Recorders\Recorder;
use Spatie\FlareClient\Contracts\Recorders\SpanEventsRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\Report;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\Container;
use Spatie\FlareClient\Tracer;

class ExceptionRecorder implements SpanEventsRecorder
{
    use RecordsSpanEvents;

    public function __construct(
        protected Tracer $tracer,
        protected BackTracer $backTracer,
        protected Container $container,
        array $config
    ) {
        $this->configure($config);
        $this->report = false; // Only trace exceptions
    }

    public static function type(): string|RecorderType
    {
        return RecorderType::Exception;
    }

    public function start(): void
    {
        $this->container->get(Flare::class);
    }

    public function record(Report $report): void
    {
        $this->persistEntry(fn () => ExceptionSpanEvent::fromFlareReport($report));
    }
}
