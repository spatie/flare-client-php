<?php

namespace Spatie\FlareClient\Recorders\JobRecorder;

use Spatie\FlareClient\AttributesProviders\PhpJobAttributesProvider;
use Spatie\FlareClient\Contracts\EntryPointHandlerProvider;
use Spatie\FlareClient\Contracts\JobAttributesProvider;
use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\EntryPoint\EntryPointResolver;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Enums\SpanStatusCode;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareMiddleware\AddJobInformation;
use Spatie\FlareClient\Recorders\SpansRecorder;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\Lifecycle;
use Spatie\FlareClient\Support\PatternMatcher;
use Spatie\FlareClient\Tracer;
use Throwable;

class JobRecorder extends SpansRecorder
{
    /** @var array<int, string> */
    protected array $ignoredClasses = [];

    public static function type(): string|RecorderType
    {
        return RecorderType::Job;
    }

    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        protected EntryPointResolver $entryPointResolver,
        protected Lifecycle $lifecycle,
        array $config,
    ) {
        parent::__construct($tracer, $backTracer, $config);
    }

    protected function configure(array $config): void
    {
        $this->ignoredClasses = $config['ignored_classes'] ?? [];
    }

    public function recordStart(
        JobAttributesProvider $jobAttributesProvider,
        ?string $traceparent = null,
        array $attributes = [],
    ): ?Span {
        $jobName = $jobAttributesProvider->jobName();
        $jobClass = $jobAttributesProvider->jobClass();

        AddJobInformation::clearLatestJobInfo();

        $shouldIgnore = $this->shouldIgnoreJob($jobName, $jobClass);

        if ($shouldIgnore && $traceparent !== null) {
            $traceparent = $this->tracer->ids->setTraceparentSampling($traceparent, false);
        }

        $this->lifecycle->startSubtask(traceparent: $traceparent);

        if ($shouldIgnore && $this->lifecycle->usesSubtasks) {
            $this->tracer->unsample();

            return null;
        }

        if ($shouldIgnore) {
            $this->pauseTrace();

            return null;
        }

        $entryPoint = new EntryPoint(
            type: EntryPointType::Queue,
            value: $jobClass ?? $jobName,
        );

        $entryPointProvider = $jobAttributesProvider instanceof EntryPointHandlerProvider ? $jobAttributesProvider : null;

        $entryPoint->setHandler(
            handlerIdentifier: $entryPointProvider?->entryPointHandlerIdentifier() ?? $jobName,
            handlerName: $entryPointProvider?->entryPointHandlerName() ?? $jobClass,
            handlerType: $entryPointProvider?->entryPointHandlerType() ?? 'php_job',
        );

        if ($this->lifecycle->usesSubtasks) {
            $this->entryPointResolver->set($entryPoint);

            $this->tracer->reevaluateSampling();
        }

        return $this->startSpan(
            name: "Job - {$jobName}",
            attributes: fn () => [
                'flare.span_type' => SpanType::Job,
                ...$entryPoint->toAttributes(),
                ...$jobAttributesProvider->toArray(),
                ...$attributes,
            ],
        );
    }

    public function recordStartFromJob(
        string $jobName,
        ?string $jobClass = null,
        ?string $traceparent = null,
        array $attributes = [],
    ): ?Span {
        return $this->recordStart(
            new PhpJobAttributesProvider($jobName, $jobClass),
            $traceparent,
            $attributes,
        );
    }

    public function recordEnd(array $attributes = []): ?Span
    {
        $span = $this->endSpan(
            additionalAttributes: $attributes,
            includeMemoryUsage: true,
        );

        if ($this->lifecycle->usesSubtasks) {
            $this->lifecycle->endSubtask();
        }

        return $span;
    }

    public function recordFailed(
        Throwable $exception,
        array $attributes = [],
    ): ?Span {
        $throwableClass = $exception::class;

        $trackingUuid = $this->tracer->ids->uuid();

        $span = $this->endSpan(
            additionalAttributes: $attributes,
            spanCallback: fn (Span $span) => $span
                ->setStatus(SpanStatusCode::Error, $exception->getMessage())
                ->addEvent(new SpanEvent(
                    name: "Exception - {$throwableClass}",
                    timestamp: $this->tracer->time->getCurrentTime(),
                    attributes: [
                        'flare.span_event_type' => SpanEventType::Exception,
                        'exception.message' => $exception->getMessage(),
                        'exception.type' => $throwableClass,
                        'exception.handled' => null,
                        'exception.id' => $trackingUuid,
                    ],
                )),
            includeMemoryUsage: true,
        );

        if ($span !== null && $this->lifecycle->usesSubtasks) {
            AddJobInformation::setEntryPoint($this->entryPointResolver->get());
            AddJobInformation::setUsedTrackingUuid($trackingUuid);
            AddJobInformation::setLatestJob($span);
        }

        if ($this->lifecycle->usesSubtasks) {
            $this->lifecycle->endSubtask();
        }

        return $span;
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
