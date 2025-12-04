<?php

namespace Spatie\FlareClient\Tests\Shared;

use Closure;
use Exception;
use Spatie\FlareClient\Contracts\WithAttributes;
use Spatie\FlareClient\Enums\OverriddenGrouping;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Tests\Shared\Concerns\ExpectAttributes;
use Spatie\FlareClient\Tests\Shared\Concerns\ExpectAttributes2;

class ExpectReport
{
    use expectAttributes2;

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

    public function expectEventCount(int $count): self
    {
        expect($this->report['events'])->toHaveCount($count);

        return $this;
    }

    public function expectEvent(int $index): ExpectReportEvent
    {
        return new ExpectReportEvent($this->report['events'][$index]);
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

    public function expectOverriddenGrouping(OverriddenGrouping $grouping): self
    {
        expect($this->report['overriddenGrouping'])->toBe($grouping);

        return $this;
    }

    public function toArray(): array
    {
        return $this->report;
    }

    protected function attributes(): array
    {
        return $this->report['attributes'];
    }
}
