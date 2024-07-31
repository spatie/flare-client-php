<?php

namespace Spatie\FlareClient\Recorders\LogRecorder;

use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Concerns\RecordsSpanEvents;
use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Contracts\SpanEventsRecorder;
use Spatie\FlareClient\Enums\MessageLevels;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Tracer;

class LogRecorder implements SpanEventsRecorder
{
    use RecordsSpanEvents;

    public static function initialize(ContainerInterface $container, array $config): static
    {
        return new self(
            tracer: $container->get(Tracer::class),
            traceLogs: $config['trace_logs'],
            reportLogs: $config['report_logs'],
            maxReportedLogs: $config['max_reported_logs'],
        );
    }

    public function __construct(
        protected Tracer $tracer,
        bool $traceLogs = true,
        bool $reportLogs = true,
        ?int $maxReportedLogs = null,
    ) {
        $this->traceSpanEvents = $traceLogs;
        $this->reportSpanEvents = $reportLogs;
        $this->maxReportedSpanEvents = $maxReportedLogs;
    }

    public function start(): void
    {

    }

    public function record(
        ?string $message,
        string $level = MessageLevels::INFO,
        array $context = [],
        ?int $time = null,
        FlareSpanEventType $spanEventType = SpanEventType::Log,
        ?array $attributes = null,
    ): LogMessageSpanEvent {
        $spanEvent = new LogMessageSpanEvent(
            message: $message,
            level: $level,
            context: $context,
            time: $time,
            spanEventType: $spanEventType,
        );

        if ($attributes) {
            $spanEvent->addAttributes($attributes);
        }

        $this->persistSpanEvent($spanEvent);

        return $spanEvent;
    }
}
