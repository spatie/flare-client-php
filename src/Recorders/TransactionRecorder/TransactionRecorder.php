<?php

namespace Spatie\FlareClient\Recorders\TransactionRecorder;

use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Contracts\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Tracer;

class TransactionRecorder implements SpansRecorder
{
    /** @var array<int, TransactionSpan> */
    protected array $stack = [];

    protected bool $traceTransactions = true;

    public static function type(): string|RecorderType
    {
        return RecorderType::Transaction;
    }

    public function __construct(
        protected Tracer $tracer,
        ?array $config = null,
    ) {
        if($config !== null) {
            $this->configure($config);
        }
    }

    public function start(): void
    {

    }

    public function configure(array $config): void
    {
        $this->traceTransactions = $config['trace'] ?? true;
    }

    public function recordBegin(
        FlareSpanType $spanType = SpanType::Transaction,
        ?array $attributes = null,
    ): ?TransactionSpan {
        if ($this->shouldTraceSpans() === false) {
            return null;
        }

        $currentSpan = $this->tracer->currentSpan();

        $span = new TransactionSpan(
            traceId: $this->tracer->currentTraceId() ?? '',
            parentSpanId: $currentSpan?->spanId,
            spanType: $spanType,
        );

        $span->addAttributes($attributes);

        $this->stack[] = $span;

        $this->tracer->addSpan($span, makeCurrent: true);

        return $span;
    }

    public function recordCommit(
        ?array $attributes = null,
    ): ?TransactionSpan {
        if ($this->shouldTraceSpans() === false) {
            return null;
        }

        $span = array_pop($this->stack);

        if ($span === null) {
            return null;
        }

        $span->addAttributes($attributes);
        $span->end();

        return $span;
    }

    public function recordRollback(
        ?array $attributes = null,
    ): ?TransactionSpan {
        if ($this->shouldTraceSpans() === false) {
            return null;
        }

        $span = array_pop($this->stack);

        if ($span === null) {
            return null;
        }

        $span->addAttributes($attributes);

        // TODO: maybe provide a reason
        $span->end();

        return $span;
    }

    public function reset(): void
    {
        $this->stack = [];
    }

    public function getSpans(): array
    {
        return []; // Only for reporting purposes
    }

    protected function shouldTraceSpans(): bool
    {
        return $this->traceTransactions && $this->tracer->isSamping();
    }
}
