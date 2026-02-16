<?php

namespace Spatie\FlareClient\Tests\Shared;

use DateTimeInterface;
use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Tests\Shared\Concerns\ExpectAttributes;
use Spatie\FlareClient\Time\TimeHelper;

class ExpectReportPrevious
{
    public function __construct(
        public array $previous,
    ) {
    }

    public function expectExceptionClass(string $exceptionClass): self
    {
        expect($this->previous['exceptionClass'])->toBe($exceptionClass);

        return $this;
    }

    public function expectMessage(string $message): self
    {
        expect($this->previous['message'])->toBe($message);

        return $this;
    }

    public function expectStacktraceCount(int $count): self
    {
        expect($this->previous['stacktrace'])->toHaveCount($count);

        return $this;
    }

    public function expectStacktraceFrame(int $index): ExpectStackTraceFrame
    {
        return new ExpectStackTraceFrame($this->previous['stacktrace'][$index]);
    }
}
