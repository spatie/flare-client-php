<?php

namespace Spatie\FlareClient\Recorders\RedisCommandRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpans;
use Spatie\FlareClient\Concerns\UsesTime;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\Recorder;
use Spatie\FlareClient\Spans\Span;

class RedisCommandRecorder extends Recorder implements SpansRecorder
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
        return $this->span(
            name: $this->resolveName($command),
            attributes: $this->resolveAttributesClosure($command, $parameters, $namespace, $serverAddress, $serverPort, $attributes),
            duration: $duration,
        );
    }

    public function recordStart(
        string $command,
        array $parameters,
        ?int $namespace = null,
        ?string $serverAddress = null,
        ?int $serverPort = null,
        array $attributes = [],
    ): ?Span {
        return $this->startSpan(
            name: $this->resolveName($command),
            attributes: $this->resolveAttributesClosure($command, $parameters, $namespace, $serverAddress, $serverPort, $attributes),
        );
    }

    public function recordEnd(
        array $attributes = [],
    ): ?Span {
        return $this->endSpan(additionalAttributes:  $attributes);
    }

    protected function resolveName(
        string $command,
    ): string {
        return "Redis - {$command}";
    }

    protected function resolveAttributesClosure(
        string $command,
        ?array $parameters,
        ?int $namespace,
        ?string $serverAddress,
        ?int $serverPort,
        array $attributes,
    ): \Closure {
        return function () use ($attributes, $serverAddress, $serverPort, $namespace, $parameters, $command) {
            return [
                'flare.span_type' => SpanType::RedisCommand,
                'db.system' => 'redis',
                'db.namespace' => $namespace,
                'db.operation.name' => $command,
                'db.query.parameters' => $parameters, // Not otel technically
                'network.peer.address' => $serverAddress,
                'network.peer.port' => $serverPort,
                ...$attributes,
            ];
        };
    }
}
