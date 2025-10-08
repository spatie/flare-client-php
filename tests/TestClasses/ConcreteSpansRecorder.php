<?php

namespace Spatie\FlareClient\Tests\TestClasses;

use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Recorders\SpansRecorder;
use Spatie\FlareClient\Spans\Span;

class ConcreteSpansRecorder extends SpansRecorder
{
    public static function type(): string|RecorderType
    {
        return 'spans';
    }

    public function pushSpan(string $name, bool $canStartTrace = false): ?Span
    {
        return $this->startSpan(name: $name, canStartTrace: $canStartTrace);
    }

    public function popSpan(): ?Span
    {
        return $this->endSpan();
    }

    public function record(
        string $name,
        int $duration,
        bool $canStartTrace = false
    ): ?Span {
        return $this->span(
            $name,
            duration: $duration,
            canStartTrace: $canStartTrace,
        );
    }

    public function resumeTrace(?string $traceParent): void
    {
        $this->potentiallyResumeTrace($traceParent);
    }
}
