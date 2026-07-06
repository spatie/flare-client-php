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

    public function pushSpan(string $name): ?Span
    {
        return $this->startSpan(name: $name);
    }

    public function popSpan(): ?Span
    {
        return $this->endSpan();
    }

    public function record(
        string $name,
        int $duration,
        array $attributes = [],
    ): ?Span {
        return $this->span(
            $name,
            attributes: $attributes,
            duration: $duration,
        );
    }

    protected function shouldTrimAttributes(): bool
    {
        return true;
    }

    public function pause(): void
    {
        $this->pauseTrace();
    }

    public static function currentPauseDepth(): int
    {
        return self::$pauseDepth;
    }

    public static function pauseOwnedBy(?ConcreteSpansRecorder $recorder): bool
    {
        return self::$pauseOwner === $recorder;
    }
}
