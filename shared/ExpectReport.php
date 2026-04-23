<?php

namespace Spatie\FlareClient\Tests\Shared;

use Closure;
use Exception;
use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Contracts\WithAttributes;
use Spatie\FlareClient\Enums\OverriddenGrouping;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Tests\Shared\Concerns\ExpectAttributes;

class ExpectReport
{
    use ExpectAttributes;

    /** @var array<int, ExpectReportEvent> */
    public array $expectReportEvents;

    public static function create(array|ReportFactory $report): self
    {
        return is_array($report) ? self::createFromReport($report) : self::createFromReportFactory($report);
    }

    public static function createFromReportFactory(ReportFactory $reportFactory): self
    {
        return self::createFromReport($reportFactory->toArray());
    }

    public static function createFromReport(array $report): self
    {
        return new self($report);
    }

    public function __construct(
        protected array $report,
    ) {
        $this->expectReportEvents = array_map(
            fn (array $event) => new ExpectReportEvent($event),
            $this->report['events'] ?? []
        );
    }

    public function expectExceptionClass(string $exceptionClass): self
    {
        expect($this->report['exceptionClass'])->toBe($exceptionClass);

        return $this;
    }

    public function expectMessage(string $message): self
    {
        expect($this->report['message'])->toBe($message);

        return $this;
    }

    public function expectHandled(?bool $handled = true): self
    {
        expect($this->report['handled'])->toBe($handled);

        return $this;
    }

    public function expectTrackingUuid(string $uuid): self
    {
        expect($this->report['trackingUuid'])->toBe($uuid);

        return $this;
    }

    public function expectApplicationPath(?string $path): self
    {
        expect($this->report['applicationPath'])->toBe($path);

        return $this;
    }

    public function expectLevel(string $level): self
    {
        expect($this->report['level'])->toBe($level);

        return $this;
    }

    public function expectSolutionCount(int $count): self
    {
        expect($this->report['solutions'])->toHaveCount($count);

        return $this;
    }

    public function expectEventCount(int $count, null|FlareSpanType|FlareSpanEventType $type = null): self
    {
        $events = $this->expectReportEvents;

        if ($type !== null) {
            $events = array_filter($events, fn (ExpectReportEvent $span) => $span->type === $type->value);
        }

        expect($events)->toHaveCount($count);

        return $this;
    }

    public function expectEvent(int|FlareSpanType|FlareSpanEventType $index): ExpectReportEvent
    {
        if (is_int($index)) {
            return $this->expectReportEvents[$index];
        }

        $expectedSpan = null;

        $this->expectEvents(
            $index,
            function (ExpectReportEvent $event) use (&$expectedSpan) {
                $expectedSpan = $event;
            }
        );

        return $expectedSpan;
    }

    public function expectEvents(FlareSpanType|FlareSpanEventType $type, Closure ...$closures): self
    {
        $eventsWithType = array_values(array_filter(
            $this->expectReportEvents,
            fn (ExpectReportEvent $event) => $event->type === $type->value
        ));

        $expectedCount = count($closures);
        $realCount = count($eventsWithType);

        expect($eventsWithType)->toHaveCount($expectedCount, "Expected to find {$expectedCount} report events of type {$type->value} but found {$realCount}.");

        foreach ($closures as $i => $closure) {
            $closure($eventsWithType[$i]);
        }

        return $this;
    }


    public function expectStacktraceCount(int $count): self
    {
        expect($this->report['stacktrace'])->toHaveCount($count);

        return $this;
    }

    public function expectStacktraceFrame(int $index): ExpectStackTraceFrame
    {
        return new ExpectStackTraceFrame($this->report['stacktrace'][$index]);
    }

    public function expectPreviousCount(int $count): self
    {
        expect($this->report['previous'])->toHaveCount($count);

        return $this;
    }

    public function expectPrevious(int $index): ExpectReportPrevious
    {
        return new ExpectReportPrevious($this->report['previous'][$index]);
    }

    public function expectOverriddenGrouping(OverriddenGrouping $grouping): self
    {
        expect($this->report['overriddenGrouping'])->toBe($grouping);

        return $this;
    }

    public function toArray(): array
    {
        return $this->report;
    }

    public function attributes(): array
    {
        return $this->report['attributes'];
    }
}
