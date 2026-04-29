<?php

namespace Spatie\FlareClient\Recorders\QueueRecorder;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\SpansRecorder;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\PatternMatcher;
use Spatie\FlareClient\Tracer;

class QueueRecorder extends SpansRecorder
{
    /** @var array<int, string> */
    protected array $ignoredClasses = [];

    public static function type(): string|RecorderType
    {
        return RecorderType::Queue;
    }

    public static function register(ContainerInterface $container, array $config): Closure
    {
        return fn () => new static(
            $container->get(Tracer::class),
            $container->get(BackTracer::class),
            $config,
        );
    }

    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        array $config,
    ) {
        parent::__construct($tracer, $backTracer, $config);
    }

    protected function configure(array $config): void
    {
        $this->ignoredClasses = $config['ignored_classes'] ?? [];
    }

    public function recordStart(
        string $jobName,
        ?string $jobClass = null,
        array $attributes = [],
    ): ?Span {
        if ($this->shouldIgnoreJob($jobName, $jobClass)) {
            $this->tracer->pauseSampling();

            return null;
        }

        return $this->startSpan(
            name: "Queueing - {$jobName}",
            attributes: [
                'flare.span_type' => SpanType::QueueingJob,
                ...$attributes,
            ],
        );
    }

    public function recordEnd(array $attributes = []): ?Span
    {
        if ($this->tracer->isSamplingPaused()) {
            $this->tracer->resumeSampling();

            return null;
        }

        return $this->endSpan(additionalAttributes: $attributes);
    }

    protected function shouldIgnoreJob(?string $jobName, ?string $jobClass = null): bool
    {
        $ignoredNames = [...$this->ignoredClasses, ...$this->defaultIgnoredJobNames()];
        $ignoredClasses = [...$this->ignoredClasses, ...$this->defaultIgnoredJobClasses()];

        if ($jobName !== null && PatternMatcher::matchesAny($jobName, $ignoredNames)) {
            return true;
        }

        if ($jobClass !== null && PatternMatcher::matchesAny($jobClass, $ignoredClasses)) {
            return true;
        }

        return false;
    }

    /** @return array<int, string> */
    protected function defaultIgnoredJobNames(): array
    {
        return [];
    }

    /** @return array<int, class-string> */
    protected function defaultIgnoredJobClasses(): array
    {
        return [];
    }
}
