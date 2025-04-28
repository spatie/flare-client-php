<?php

namespace Spatie\FlareClient\Recorders\RedisCommandRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpans;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\Recorder;
use Spatie\FlareClient\Spans\Span;

class RedisCommandRecorder  extends Recorder  implements SpansRecorder
{
    /** @use RecordsSpans<Span> */
    use RecordsSpans;

    public static function type(): string|RecorderType
    {
        return RecorderType::RedisCommand;
    }

    public function record(
        string $command,
        array $parameters,
        int $duration,
        ?int $namespace = null,
        ?string $serverAddress = null,
        ?int $serverPort = null,
        FlareSpanType $spanType = SpanType::RedisCommand,
        ?int $end = null,
        ?array $attributes = null,
    ): ?Span {
        return $this->persistEntry(function () use ($serverPort, $namespace, $serverAddress, $parameters, $command, $end, $attributes, $spanType, $duration) {
            $span = Span::build(
                traceId: $this->tracer->currentTraceId() ?? '',
                parentId: $this->tracer->currentSpan()?->spanId,
                name: "Redis - {$command}",
                end: $end,
                duration: $duration,
                attributes: [
                    'flare.span_type' => $spanType,
                    'db.system' => 'redis',
                    'db.namespace' => $namespace,
                    'db.operation.name' => $command,
                    'db.query.parameters' => $parameters, // Not otel technically
                    'network.peer.address' => $serverAddress,
                    'network.peer.port' => $serverPort,
                ]
            );

            $span->addAttributes($attributes);

            return $span;
        });
    }
}
