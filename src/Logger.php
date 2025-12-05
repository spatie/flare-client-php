<?php

namespace Spatie\FlareClient;

use DateTimeInterface;
use Spatie\FlareClient\Exporters\Exporter;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Time\Time;
use Spatie\FlareClient\Time\TimeHelper;

/**
 * @phpstan-type LogRecord array{time_unix_nano: int, observed_time_unix_nano: int, trace_id?: string, span_id?: string, flags?: string, severity_text?: string, severity_number?: int, body: mixed, attributes?: array<string, mixed>}
 */
class Logger
{
    /** @var array<int, LogRecord> */
    protected array $logs = [];

    public function __construct(
        protected readonly Api $api,
        protected readonly Time $time,
        protected readonly Exporter $exporter,
        protected readonly Tracer $tracer,
        protected readonly Resource $resource,
        protected readonly Scope $scope,
        protected readonly bool $disabled,
    ) {
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
        $timestampUnixNano ??= $this->time->getCurrentTime();

        if (! is_int($timestampUnixNano)) {
            $timestampUnixNano = TimeHelper::dateTimeToNano($timestampUnixNano);
        }

        $observedTimestampUnixNano ??= $timestampUnixNano;

        if (! is_int($observedTimestampUnixNano)) {
            $observedTimestampUnixNano = TimeHelper::dateTimeToNano($observedTimestampUnixNano);
        }

        $record = array_filter([
                'time_unix_nano' => $timestampUnixNano,
                'observed_time_unix_nano' => $observedTimestampUnixNano,
                'trace_id' => $traceId ?? $this->tracer->currentTraceId(),
                'span_id' => $spanId ?? $this->tracer->currentSpanId(),
                'flags' => $flags ?? ($this->tracer->isSampling() ? '01' : '00'),
                'severity_text' => $severityText,
                'severity_number' => $severityNumber,
                'attributes' => $attributes,
            ]) + [
                'body' => $body,
            ];

        $this->logs[] = $record;
    }

    public function flush(): void
    {
        if (count($this->logs) === 0) {
            return;
        }

        $logData = $this->exporter->logs(
            $this->resource,
            $this->scope,
            $this->logs,
        );

        $this->api->log(
            $logData
        );
    }
}
