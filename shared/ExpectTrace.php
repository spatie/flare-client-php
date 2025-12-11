<?php

namespace Spatie\FlareClient\Tests\Shared;

use Closure;
use Exception;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Enums\SpanType;

class ExpectTrace
{
    /** @var array<int, ExpectSpan> */
    protected array $expectSpans;

    public static function create(array $trace): self
    {
        return new self($trace);
    }

    public function __construct(
        public array $trace
    ) {
        $this->expectSpans = array_map(
            fn (array $span) => new ExpectSpan($span),
            $this->trace['resourceSpans'][0]['scopeSpans'][0]['spans'],
        );
    }

    public function expectSpan(int|FlareSpanType $index): ExpectSpan
    {
        if (is_int($index)) {
            return $this->expectSpans[$index];
        }

        $spansWithType = array_filter(
            $this->expectSpans,
            fn (ExpectSpan $span) => $span->type === $index->value,
        );

        expect($spansWithType)->toHaveCount(1, "More than one or no span with type {$index->value} found.");

        return array_values($spansWithType)[0];
    }

    public function expectSpanCount(int $count, ?FlareSpanType $type = null): self
    {
        $spans = $this->expectSpans;

        if ($type !== null) {
            $spans = array_filter($spans, fn (ExpectSpan $span) => $span->type === $type->value);
        }

        expect($spans)->toHaveCount($count);

        return $this;
    }

    public function expectNoSpans(): self
    {
        expect($this->expectSpans)->toBeEmpty();

        return $this;
    }

    public function expectResource(): ExpectResource
    {
        return new ExpectResource($this->trace['resourceSpans'][0]['resource']);
    }

    public function expectScope(): ExpectScope
    {
        return ExpectScope::create($this->trace['resourceSpans'][0]['scopeSpans'][0]['scope']);
    }

    public function expectAllSpansClosed(): self
    {
        foreach ($this->expectSpans as $expectSpan) {
            expect($expectSpan->span['endTimeUnixNano'] ?? null)->not->toBeNull("Span with ID {$expectSpan->span['spanId']} is not closed.");
            expect($expectSpan->span['startTimeUnixNano'] ?? null)->not->toBeNull("Span with ID {$expectSpan->span['spanId']} is not closed.");
        }

        return $this;
    }

    /**
     * @param (Closure(&$spanIndex int, $currentSpan ExpectSpan):void)|null $applicationSpans
     * @param (Closure(&$spanIndex int, $currentSpan ExpectSpan):void)|null $terminatingSpans
     */
    public function expectLifecycle(
        bool $registration = true,
        bool $boot = true,
        Closure|null $applicationSpans = null,
        bool $terminating = true,
        Closure|null $terminatingSpans = null,
    ): self {
        $spanIndex = 0;

        $applicationSpan = $this->expectSpan($spanIndex++)
            ->expectMissingParentId()
            ->expectType(SpanType::Application);

        if ($registration) {
            $registrationSpan = $this->expectSpan($spanIndex++)
                ->expectParentId($applicationSpan)
                ->expectType(SpanType::ApplicationRegistration);
        }

        if ($boot) {
            $bootSpan = $this->expectSpan($spanIndex++)
                ->expectParentId($applicationSpan)
                ->expectType(SpanType::ApplicationBoot);
        }

        if ($applicationSpans) {
            $applicationSpans($spanIndex, $applicationSpan);
        }

        if ($terminating) {
            while (true) {
                if ($spanIndex === count($this->expectSpans)) {
                    throw new Exception('could not find terminating span');
                }

                $currentSpan = $this->expectSpan($spanIndex);

                if ($currentSpan->type === SpanType::ApplicationTerminating->value) {
                    break;
                }

                $spanIndex++;
            }

            $terminatingSpan = $this->expectSpan($spanIndex)
                ->expectParentId($applicationSpan)
                ->expectType(SpanType::ApplicationTerminating);
        }

        if ($terminatingSpans && $terminating) {
            $terminatingSpans($spanIndex);
        }

        $this->expectAllSpansClosed();

        return $this;
    }

    /**
     * @param (Closure(&$spanIndex int, $currentSpan ExpectSpan):void)|null $requestSpans
     * @param (Closure(&$spanIndex int, $currentSpan ExpectSpan):void)|null $terminatingSpans
     */
    public function expectRequestLifecycle(
        bool $registration = true,
        bool $boot = true,
        bool $globalBeforeMiddleware = true,
        bool $routing = true,
        bool $beforeMiddleware = true,
        Closure|null $requestSpans = null,
        bool $afterMiddleware = true,
        bool $globalAfterMiddleware = true,
        bool $terminating = true,
        Closure|null $terminatingSpans = null,
    ): self {
        return $this->expectLifecycle(
            registration: $registration,
            boot: $boot,
            applicationSpans: function (&$spanIndex, $applicationSpan) use (
                $requestSpans,
                $globalBeforeMiddleware,
                $routing,
                $beforeMiddleware,
                $afterMiddleware,
                $globalAfterMiddleware
            ) {
                $requestSpan = $this->expectSpan($spanIndex++)
                    ->expectParentId($applicationSpan)
                    ->expectType(SpanType::Request);

                if ($globalBeforeMiddleware) {
                    $globalBeforeMiddlewareSpan = $this->expectSpan($spanIndex++)
                        ->expectParentId($requestSpan)
                        ->expectType(SpanType::GlobalBeforeMiddleware);
                }

                if ($routing) {
                    $routingSpan = $this->expectSpan($spanIndex++)
                        ->expectParentId($requestSpan)
                        ->expectType(SpanType::Routing);
                }

                if ($beforeMiddleware) {
                    $beforeMiddlewareSpan = $this->expectSpan($spanIndex++)
                        ->expectParentId($requestSpan)
                        ->expectType(SpanType::BeforeMiddleware);
                }

                if ($requestSpans) {
                    $requestSpans($spanIndex, $requestSpan);
                }

                if ($afterMiddleware) {
                    $afterMiddlewareSpan = $this->expectSpan($spanIndex++)
                        ->expectParentId($requestSpan)
                        ->expectType(SpanType::AfterMiddleware);
                }

                if ($globalAfterMiddleware) {
                    $globalAfterMiddlewareSpan = $this->expectSpan($spanIndex++)
                        ->expectParentId($requestSpan)
                        ->expectType(SpanType::GlobalAfterMiddleware);
                }
            },
            terminating: $terminating,
            terminatingSpans: $terminatingSpans,
        );
    }

    /**
     * @param (Closure(&$spanIndex int, $currentSpan ExpectSpan):void)|null $requestSpans
     * @param (Closure(&$spanIndex int, $currentSpan ExpectSpan):void)|null $terminatingSpans
     */
    public function expectLaravelRequestLifecycle(
        Closure|null $requestSpans = null,
        Closure|null $terminatingSpans = null,
    ): self {
        return $this->expectRequestLifecycle(
            requestSpans: $requestSpans,
            afterMiddleware: false,
            globalAfterMiddleware: false,
            terminatingSpans: $terminatingSpans,
        );
    }

    public function toArray(): array
    {
        return $this->trace;
    }
}
