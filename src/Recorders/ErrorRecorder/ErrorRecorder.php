<?php

namespace Spatie\FlareClient\Recorders\ErrorRecorder;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Concerns\Recorders\RecordsSpanEvents;
use Spatie\FlareClient\Contracts\Recorders\SpanEventsRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanStatusCode;
use Spatie\FlareClient\Recorders\Recorder;
use Spatie\FlareClient\Report;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Tracer;

class ErrorRecorder extends Recorder implements SpanEventsRecorder
{
    /** @use RecordsSpanEvents<SpanEvent> */
    use RecordsSpanEvents;

    const DEFAULT_WITH_TRACES = true;

    const DEFAULT_WITH_ERRORS = false;

    public function __construct(
        protected Tracer $tracer,
    ) {
        $this->withTraces = true;
    }

    public static function register(ContainerInterface $container, array $config): Closure
    {
        return fn () => new self(
            $container->get(Tracer::class),
        );
    }

    public static function type(): string|RecorderType
    {
        return RecorderType::Exception;
    }

    public function record(Report $report): void
    {
        $this->persistEntry(function () use ($report) {
            $event = ErrorSpanEvent::fromReport(
                $report,
                $this->tracer->time->getCurrentTime(),
            );

            $currentSpan = $this->tracer->currentSpan();

            if ($currentSpan === null) {
                return $event;
            }

            if ($event->handled !== true) {
                $currentSpan->setStatus(
                    SpanStatusCode::Error,
                    $event->message,
                );
            }

            return $event;
        });
    }
}
