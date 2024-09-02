<?php

namespace Spatie\FlareClient\Tests\Shared;

use Spatie\FlareClient\Recorders\GlowRecorder\GlowSpanEvent;
use Spatie\FlareClient\Report;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\Ids;
use Spatie\FlareClient\Tracer;

class FakeIds extends Ids
{
    public static array $nextTraceIds = [];

    public static array $nextSpanIds = [];

    public static array $nextUuids = [];

    public static function setup(): self
    {
        $fakeIds = new FakeIds();

        Tracer::useIds($fakeIds);
        Span::useIds($fakeIds);
        ReportFactory::useIds($fakeIds);

        return $fakeIds;
    }

    public function trace(): string
    {
        return array_shift(static::$nextTraceIds) ?? parent::trace();
    }

    public function span(): string
    {
        return array_shift(static::$nextSpanIds) ?? parent::span();
    }

    public function uuid(): string
    {
        return array_shift(static::$nextUuids) ?? parent::uuid();
    }

    public function nextTraceId(string $traceId): self
    {
        static::$nextTraceIds[] = $traceId;

        return $this;
    }

    public function nextSpanId(string $spanId): self
    {
        static::$nextSpanIds[] = $spanId;

        return $this;
    }

    public function nextUuid(string $uuid): self
    {
        static::$nextUuids[] = $uuid;

        return $this;
    }

    public static function reset(): void
    {
        static::$nextTraceIds = [];
        static::$nextSpanIds = [];
        static::$nextUuids = [];
    }
}
