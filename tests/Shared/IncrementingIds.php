<?php

namespace Spatie\FlareClient\Tests\Shared;

use Ramsey\Uuid\Uuid;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\Ids;
use Spatie\FlareClient\Tracer;

class IncrementingIds extends Ids
{
    public static function setup(): self
    {
        $generator = new self();

        Tracer::useIds($generator);
        Span::useIds($generator);
        ReportFactory::useIds($generator);

        return $generator;
    }

    protected static int $traceId = 0;

    protected static int $spanId = 0;

    public function trace(): string
    {
        return static::$traceId++;
    }

    public function span(): string
    {
        return static::$spanId++;
        ;
    }

    public function uuid(): string
    {
        return (string) Uuid::uuid7();
    }

    public static function reset(): void
    {
        static::$traceId = 0;
        static::$spanId = 0;
    }
}
