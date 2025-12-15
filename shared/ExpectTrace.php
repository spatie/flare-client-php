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

        $expectedSpan = null;

        $this->expectSpans(
            $index,
            function (ExpectSpan $span) use (&$expectedSpan) {
                $expectedSpan = $span;
            }
        );

        return $expectedSpan;
    }

    /**
     * @param Closure(ExpectSpan):void ...$closures
     */
    public function expectSpans(
        FlareSpanType $type,
        Closure ...$closures
    ): self
    {
        $spansWithType = array_values(array_filter(
            $this->expectSpans,
            fn (ExpectSpan $span) => $span->type === $type->value
        ));

        $expectedCount = count($closures);
        $realCount = count($spansWithType);

        expect($spansWithType)->toHaveCount($expectedCount, "Expected to find {$expectedCount} spans of type {$type->value} but found {$realCount}.");

        foreach ($closures as $i => $closure) {
            $closure($spansWithType[$i]);
        }

        return $this;
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

            if($terminatingSpans){
                $spanIndex++;

                $terminatingSpans($spanIndex, $terminatingSpan);
            }
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

    public function dump(): self
    {
        $output = [];

        foreach ($this->expectSpans as $expectSpan) {
            $parentId = $expectSpan->span['parentSpanId'] ?? null;
            $name = $expectSpan->span['name'];
            $type = $expectSpan->type;

            $indent = $this->getIndentLevel($parentId);
            $prefix = str_repeat('  ', $indent);

            if ($indent > 0) {
                $prefix .= '├─ ';
            }

            $output[] = "{$prefix}{$name}" . ($type ? " ({$type})" : '');

            $filteredAttributes = array_filter(
                $expectSpan->attributes(),
                fn ($key) => ! in_array($key, ['flare.span_type', 'flare.span_event_type']),
                ARRAY_FILTER_USE_KEY
            );

            if (! empty($filteredAttributes)) {
                $attributePrefix = str_repeat('  ', $indent);
                if ($indent > 0) {
                    $attributePrefix .= '│  ';
                }

                foreach ($filteredAttributes as $key => $value) {
                    $valueStr = is_array($value) ? json_encode($value) : (string) $value;
                    $output[] = "{$attributePrefix}• {$key}: {$valueStr}";
                }
            }
        }

        dump(implode("\n", $output));

        return $this;
    }

    protected function getIndentLevel(?string $parentId): int
    {
        if ($parentId === null) {
            return 0;
        }

        $level = 1;
        $currentParentId = $parentId;

        while ($currentParentId !== null) {
            $parentSpan = null;

            foreach ($this->expectSpans as $span) {
                if ($span->span['spanId'] === $currentParentId) {
                    $parentSpan = $span;
                    break;
                }
            }

            if ($parentSpan === null) {
                break;
            }

            $currentParentId = $parentSpan->span['parentSpanId'] ?? null;

            if ($currentParentId !== null) {
                $level++;
            }
        }

        return $level;
    }

    public function toArray(): array
    {
        return $this->trace;
    }
}
