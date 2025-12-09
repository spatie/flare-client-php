<?php

namespace Spatie\FlareClient\Support;

use Closure;
use Spatie\FlareClient\Api;
use Spatie\FlareClient\Enums\LifecycleStage;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Logger;
use Spatie\FlareClient\Memory\Memory;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Time\Time;
use Spatie\FlareClient\Time\TimeHelper;
use Spatie\FlareClient\Tracer;

class Lifecycle
{
    public readonly bool $usesSubtasks;

    /**
     * @param Closure():bool|null $isUsingSubtasksClosure
     * @param Closure(bool):bool|null $shouldMakeSamplingDecisionClosure
     */
    public function __construct(
        protected Api $api,
        protected Time $time,
        protected Memory $memory,
        protected Logger $logger,
        protected Tracer $tracer,
        protected Recorders $recorders,
        protected SentReports $sentReports,
        protected Resource $resource,
        protected LifecycleStage $stage = LifecycleStage::Idle,
        protected ?Closure $isUsingSubtasksClosure = null,
        protected ?Closure $shouldMakeSamplingDecisionClosure = null,
    ) {
        $this->usesSubtasks = $this->isUsingSubtasks();

        register_shutdown_function([$this, 'shutdown']);
    }

    protected function isUsingSubtasks(): bool
    {
        if ($this->isUsingSubtasksClosure) {
            return ($this->isUsingSubtasksClosure)();
        }

        return false;
    }

    protected function shouldPotentiallySampleTrace(?string $traceparent): bool
    {
        if ($traceparent) {
            return true;
        }

        if ($this->shouldMakeSamplingDecisionClosure) {
            return ($this->shouldMakeSamplingDecisionClosure)($this->usesSubtasks);
        }

        return true;
    }

    public function start(
        ?int $timeUnixNano = null,
        array $attributes = [],
        ?string $traceparent = null,
        array $samplerContext = [],
    ): void {
        if ($this->usesSubtasks || $this->stage === LifecycleStage::Started) {
            return;
        }

        if ($this->stage !== LifecycleStage::Idle) {
            $this->trash();

            return;
        }

        $this->stage = LifecycleStage::Started;

        if ($this->shouldPotentiallySampleTrace($traceparent) === false) {
            return;
        }

        $this->tracer->startTrace(samplerContext: $samplerContext, traceParent: $traceparent);

        if ($this->tracer->sampling === false) {
            return;
        }

        $serviceName = $this->resource->serviceName;

        $timeUnixNano ??= match (true) {
            array_key_exists('REQUEST_TIME_FLOAT', $_SERVER) => TimeHelper::phpMicroTime($_SERVER['REQUEST_TIME_FLOAT']),
            default => $this->time->getCurrentTime(),
        };

        $this->tracer->startSpan(
            $serviceName ? "App - {$serviceName}" : 'App',
            time: $timeUnixNano,
            attributes: [
                'flare.span_type' => SpanType::Application,
                ...$attributes,
            ],
        );
    }

    public function register(
        ?int $timeUnixNano = null,
        array $attributes = [],
    ): void {
        if ($this->usesSubtasks || $this->stage === LifecycleStage::Registering) {
            return;
        }

        if ($this->stage !== LifecycleStage::Started) {
            $this->trash();

            return;
        }

        $this->stage = LifecycleStage::Registering;

        if ($this->tracer->sampling === false) {
            return;
        }

        $this->tracer->startSpan(
            name: "Registering App",
            time: $timeUnixNano,
            attributes: [
                'flare.span_type' => SpanType::ApplicationRegistration,
                ...$attributes,
            ],
        );
    }

    public function registered(
        ?int $timeUnixNano = null,
        array $additionalAttributes = [],
    ): void {
        if ($this->usesSubtasks || $this->stage === LifecycleStage::Registered) {
            return;
        }

        if ($this->stage !== LifecycleStage::Registering) {
            $this->trash();

            return;
        }

        $this->stage = LifecycleStage::Registered;

        if ($this->tracer->sampling === false) {
            return;
        }

        $this->tracer->endSpan(
            time: $timeUnixNano ?? $this->time->getCurrentTime(),
            additionalAttributes: $additionalAttributes,
        );
    }

    public function boot(
        ?int $timeUnixNano = null,
        array $attributes = [],
    ): void {
        if ($this->usesSubtasks || $this->stage === LifecycleStage::Booting) {
            return;
        }

        if ($this->stage === LifecycleStage::Registering) {
            $this->registered();
        }

        if ($this->stage !== LifecycleStage::Registered && $this->stage !== LifecycleStage::Started) {
            $this->trash();

            return;
        }

        $this->stage = LifecycleStage::Booting;

        if ($this->tracer->sampling === false) {
            return;
        }

        $this->tracer->startSpan(
            name: "Booting App",
            time: $timeUnixNano ?? $this->time->getCurrentTime(),
            attributes: [
                'flare.span_type' => SpanType::ApplicationBoot,
                ...$attributes,
            ],
        );
    }

    public function booted(
        ?int $timeUnixNano = null,
        array $additionalAttributes = [],
    ): void {
        if ($this->usesSubtasks || $this->stage === LifecycleStage::Booted) {
            return;
        }

        if ($this->stage !== LifecycleStage::Booting) {
            $this->trash();

            return;
        }

        $this->stage = LifecycleStage::Booted;

        if ($this->tracer->sampling === false) {
            return;
        }

        $this->tracer->endSpan(
            time: $timeUnixNano ?? $this->time->getCurrentTime(),
            additionalAttributes: $additionalAttributes,
        );
    }

    public function startSubtask(
        ?string $traceparent = null,
        array $samplerContext = [],
    ): void {
        if ($this->usesSubtasks === false || $this->stage === LifecycleStage::Subtask) {
            return;
        }

        if ($this->stage !== LifecycleStage::Idle) {
            $this->trash();

            return;
        }

        if ($this->shouldPotentiallySampleTrace($traceparent) === false) {
            return;
        }


        $this->stage = LifecycleStage::Subtask;

        $this->tracer->startTrace(
            samplerContext: $samplerContext,
            traceParent: $traceparent,
        );
    }

    public function endSubtask(): void
    {
        if ($this->usesSubtasks === false || $this->stage === LifecycleStage::Idle) {
            return;
        }

        if ($this->stage !== LifecycleStage::Subtask) {
            $this->trash();

            return;
        }

        $this->stage = LifecycleStage::Idle;

        if ($this->tracer->sampling === true) {
            $this->tracer->endTrace();
        }

        $this->flush();
        $this->memory->resetPeaMemoryUsage();
    }

    public function terminating(
        ?int $timeUnixNano = null,
        array $attributes = [],
    ): void {
        if ($this->usesSubtasks || $this->stage === LifecycleStage::Terminating) {
            return;
        }

        if ($this->stage !== LifecycleStage::Booted
            && $this->stage !== LifecycleStage::Registered
            && $this->stage !== LifecycleStage::Started
        ) {
            $this->trash();

            return;
        }

        $this->stage = LifecycleStage::Terminating;

        if ($this->tracer->sampling === false) {
            return;
        }

        $this->tracer->startSpan(
            name: "Terminating App",
            time: $timeUnixNano ?? $this->time->getCurrentTime(),
            attributes: [
                'flare.span_type' => SpanType::ApplicationTerminating,
                ...$attributes,
            ],
        );
    }

    public function terminated(
        ?int $timeUnixNano = null,
        array $additionalTerminationAttributes = [],
        array $additionalApplicationAttributes = [],
    ): void {
        if ($this->usesSubtasks || $this->stage === LifecycleStage::Terminated) {
            return;
        }

        $shouldEndTerminatingSpan = $this->stage === LifecycleStage::Terminating;

        if ($this->stage !== LifecycleStage::Terminating
            && $this->stage !== LifecycleStage::Booted
            && $this->stage !== LifecycleStage::Registered
            && $this->stage !== LifecycleStage::Started
        ) {
            $this->trash();

            return;
        }

        $this->stage = LifecycleStage::Terminated;

        if ($this->tracer->sampling === false) {
            return;
        }

        if (! $shouldEndTerminatingSpan) {
            $this->tracer->endSpan(
                time: $timeUnixNano ?? $this->time->getCurrentTime(),
                additionalAttributes: $additionalApplicationAttributes,
            );

            $this->tracer->endTrace();

            return;
        }

        $this->tracer->endSpan(
            time: $timeUnixNano ?? $this->time->getCurrentTime(),
            additionalAttributes: $additionalTerminationAttributes,
        );

        $this->tracer->endSpan(
            time: $timeUnixNano ?? $this->time->getCurrentTime(),
            additionalAttributes: $additionalApplicationAttributes,
        );

        $this->tracer->endTrace();

        $this->flush();
    }

    protected function shutdown(): void
    {
        if (! $this->tracer->sampling) {
            $this->flush();

            return;
        }

        $this->tracer->gracefullyEndSpans(force: true);

        $canEndTrace = true;

        foreach ($this->tracer->currentTrace() as $span) {
            if ($span->end === null) {
                $canEndTrace = false;

                break;
            }
        }

        if ($canEndTrace) {
            $this->tracer->endTrace();
        }

        $this->flush();
    }

    public function trash(): void
    {
        $this->tracer->trashTrace();
        $this->stage = LifecycleStage::Idle;

        $this->flush();
    }

    public function flush(): void
    {
        $this->logger->flush();
        $this->api->sendQueue();

        $this->sentReports->clear();
        $this->recorders->reset();
    }

    public function getStage(): LifecycleStage
    {
        return $this->stage;
    }
}
