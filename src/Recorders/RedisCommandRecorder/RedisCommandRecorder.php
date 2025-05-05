<?php

namespace Spatie\FlareClient\Recorders\RedisCommandRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpans;
use Spatie\FlareClient\Concerns\UsesTime;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\Recorder;
use Spatie\FlareClient\Spans\Span;

class RedisCommandRecorder  extends Recorder  implements SpansRecorder
{
    use UsesTime;

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
        array $attributes = [],
    ): ?Span {
        $span = $this->recordStart(
            command: $command,
            parameters: $parameters,
            start: self::getCurrentTime() - $duration,
            namespace: $namespace,
            serverAddress: $serverAddress,
            serverPort: $serverPort,
            attributes: $attributes,
        );

        if ($span === null) {
            return null;
        }

        $this->recordEnd();

        $this->setOrigin($span);

        return $span;
    }

    public function recordStart(
        string $command,
        array $parameters,
        int $start,
        ?int $namespace = null,
        ?string $serverAddress = null,
        ?int $serverPort = null,
        array $attributes = [],
    ): ?Span {
        return $this->startSpan(function () use ($serverPort, $parameters, $serverAddress, $namespace, $command, $start, $attributes,) {
            return Span::build(
                traceId: $this->tracer->currentTraceId() ?? '',
                parentId: $this->tracer->currentSpan()?->spanId,
                name: "Redis - {$command}",
                start: $start,
                attributes: [
                    'flare.span_type' => SpanType::RedisCommand,
                    'db.system' => 'redis',
                    'db.namespace' => $namespace,
                    'db.operation.name' => $command,
                    'db.query.parameters' => $parameters, // Not otel technically
                    'network.peer.address' => $serverAddress,
                    'network.peer.port' => $serverPort,
                    ...$attributes,
                ]
            );
        });
    }

    public function recordEnd(
        array $attributes = [],
    ): ?Span {
        return $this->endSpan(
            attributes: $attributes,
        );
    }
}
