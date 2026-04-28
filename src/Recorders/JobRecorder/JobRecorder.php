<?php

namespace Spatie\FlareClient\Recorders\JobRecorder;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\EntryPoint\EntryPointResolver;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Enums\SpanStatusCode;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\SpansRecorder;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;
use Throwable;

class JobRecorder extends SpansRecorder
{
    public static function type(): string|RecorderType
    {
        return RecorderType::Job;
    }

    public static function register(ContainerInterface $container, array $config): Closure
    {
        return fn () => new static(
            $container->get(Tracer::class),
            $container->get(BackTracer::class),
            $container->get(EntryPointResolver::class),
            $config,
        );
    }

    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        protected EntryPointResolver $entryPointResolver,
        array $config,
    ) {
        parent::__construct($tracer, $backTracer, $config);
    }

    public function recordStart(
        string $jobName,
        ?string $jobClass = null,
        ?string $entryPointHandlerType = 'php_job',
        array $attributes = [],
    ): ?Span {
        $entryPoint = new EntryPoint(
            type: EntryPointType::Queue,
            value: $jobClass ?? $jobName,
        );

        $entryPoint->setHandler(
            handlerIdentifier: $jobName,
            handlerName: $jobClass,
            handlerType: $entryPointHandlerType,
        );

        $this->entryPointResolver->set($entryPoint);

        if ($this->shouldIgnoreJob($jobName, $jobClass)) {
            $this->tracer->unsample();

            return null;
        }

        return $this->startSpan(
            name: "Job - {$jobName}",
            attributes: [
                'flare.span_type' => SpanType::Job,
                ...$this->entryPointResolver->get()->toAttributes(),
                ...$attributes,
            ],
        );
    }

    public function recordEnd(array $attributes = []): ?Span
    {
        return $this->endSpan(
            additionalAttributes: $attributes,
            includeMemoryUsage: true,
        );
    }

    public function recordFailed(
        Throwable $exception,
        array $attributes = [],
    ): ?Span {
        $throwableClass = $exception::class;

        return $this->endSpan(
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
                    ],
                )),
            includeMemoryUsage: true,
        );
    }

    protected function shouldIgnoreJob(?string $jobName, ?string $jobClass = null): bool
    {
        if ($jobName !== null && in_array($jobName, $this->defaultIgnoredJobNames())) {
            return true;
        }

        if ($jobClass !== null && in_array($jobClass, $this->defaultIgnoredJobClasses())) {
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
