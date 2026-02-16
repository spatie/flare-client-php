<?php

namespace Spatie\FlareClient;

use DateTimeInterface;
use Monolog\Level;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Recorders\ContextRecorder\ContextRecorder;
use Spatie\FlareClient\Support\Recorders;
use Spatie\FlareClient\Support\SeverityMapper;
use Spatie\FlareClient\Time\Time;
use Spatie\FlareClient\Time\TimeHelper;

/**
 * @phpstan-type LogRecord array{timeUnixNano: int, observedTimeUnixNano: int, traceId?: string, spanId?: string, flags?: string, severityText?: string, severityNumber?: int, body: mixed, attributes?: array<string, mixed>}
 */
class Logger
{
    /** @var array<int, LogRecord> */
    protected array $logs = [];

    protected ?int $minimalSeverityNumber = null;

    public function __construct(
        protected readonly Api $api,
        protected readonly Time $time,
        protected readonly Tracer $tracer,
        protected readonly Recorders $recorders,
        protected readonly bool $disabled,
        ?Level $minimalLogLevel
    ) {
        if ($minimalLogLevel) {
            $this->minimalSeverityNumber = SeverityMapper::fromMonolog($minimalLogLevel);
        }
    }

    public function record(
        ?string $message,
        Level $level = Level::Info,
        array $context = [],
        array $attributes = [],
    ): void {
        $this->log(
            body: $message,
            severityText: strtolower($level->getName()),
            severityNumber: SeverityMapper::fromMonolog($level),
            attributes: [
                'log.context' => $context,
                ...$attributes,
            ],
        );
    }

    public function log(
        null|int|DateTimeInterface $timestampUnixNano = null,
        mixed $body = null,
        null|int|DateTimeInterface $observedTimestampUnixNano = null,
        ?string $severityText = null,
        ?int $severityNumber = null,
        ?array $attributes = null,
        ?string $traceId = null,
        ?string $spanId = null,
        ?string $flags = null,
    ): void {
        if ($this->disabled) {
            return;
        }

        if ($this->minimalSeverityNumber && $severityNumber === null) {
            return;
        }

        if ($this->minimalSeverityNumber && $severityNumber < $this->minimalSeverityNumber) {
            return;
        }

        $timestampUnixNano ??= $this->time->getCurrentTime();

        if (! is_int($timestampUnixNano)) {
            $timestampUnixNano = TimeHelper::dateTimeToNano($timestampUnixNano);
        }

        $observedTimestampUnixNano ??= $timestampUnixNano;

        if (! is_int($observedTimestampUnixNano)) {
            $observedTimestampUnixNano = TimeHelper::dateTimeToNano($observedTimestampUnixNano);
        }

        /** @var ?ContextRecorder $recorder */
        $recorder = $this->recorders->getRecorder(RecorderType::Context);

        if ($context = $recorder?->toArray()) {
            $attributes = [...$attributes ?? [], ...$context];
        }

        $record = array_filter([
                'timeUnixNano' => $timestampUnixNano,
                'observedTimeUnixNano' => $observedTimestampUnixNano,
                'traceId' => $traceId ?? $this->tracer->currentTraceId(),
                'spanId' => $spanId ?? $this->tracer->currentSpanId(),
                'flags' => $flags ?? ($this->tracer->isSampling() ? '01' : '00'),
                'severityText' => $severityText,
                'severityNumber' => $severityNumber,
                'attributes' => $attributes,
            ]) + [
                'body' => $body,
            ];

        $this->logs[] = $record;
    }

    /** @return  array<int, LogRecord> */
    public function logs(): array
    {
        return $this->logs;
    }

    public function flush(): void
    {
        if (count($this->logs) === 0) {
            return;
        }

        $this->api->log($this->logs);

        $this->logs = [];
    }
}
