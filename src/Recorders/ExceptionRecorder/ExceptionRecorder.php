<?php

namespace Spatie\FlareClient\Recorders\ExceptionRecorder;

use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Concerns\RecordsSpanEvents;
use Spatie\FlareClient\Contracts\Recorder;
use Spatie\FlareClient\Report;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;

class ExceptionRecorder implements Recorder
{
    use RecordsSpanEvents;

    public static function initialize(ContainerInterface $container, array $config): static
    {
        return new self(
            traceExceptions: $config['trace_exceptions'],
        );
    }

    public function __construct(
        bool $traceExceptions,
    ) {
        $this->traceSpanEvents = $traceExceptions;
    }

    public function start(): void
    {
        // Started by Flare
    }

    public function record(Report $report): void
    {
        $this->persistSpanEvent(ExceptionSpanEvent::fromFlareReport($report));
    }
}


