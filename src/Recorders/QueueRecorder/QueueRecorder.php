<?php

namespace Spatie\FlareClient\Recorders\QueueRecorder;

use Spatie\FlareClient\AttributesProviders\PhpJobAttributesProvider;
use Spatie\FlareClient\Contracts\JobAttributesProvider;
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
        JobAttributesProvider $provider,
        array $attributes = [],
    ): ?Span {
        $jobName = $provider->jobName();
        $jobClass = $provider->jobClass();

        if ($this->shouldIgnoreJob($jobName, $jobClass)) {
            $this->pauseTrace();

            return null;
        }

        return $this->startSpan(
            name: "Queueing - {$jobName}",
            attributes: [
                'flare.span_type' => SpanType::QueueingJob,
                ...$provider->toArray(),
                ...$attributes,
            ],
        );
    }

    public function recordStartFromQueuedJob(
        string $jobName,
        ?string $jobClass = null,
        array $attributes = [],
    ): ?Span {
        return $this->recordStart(
            new PhpJobAttributesProvider($jobName, $jobClass),
            $attributes,
        );
    }

    public function recordEnd(array $attributes = []): ?Span
    {
        return $this->endSpan(additionalAttributes: $attributes);
    }

    protected function shouldIgnoreJob(string $jobName, ?string $jobClass = null): bool
    {
        $ignoredNames = [...$this->ignoredClasses, ...$this->defaultIgnoredJobNames()];
        $ignoredClasses = [...$this->ignoredClasses, ...$this->defaultIgnoredJobClasses()];

        if (PatternMatcher::matchesAny($jobName, $ignoredNames)) {
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
