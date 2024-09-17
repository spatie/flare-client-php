<?php

namespace Spatie\FlareClient\Tests\Shared;

use DateTimeImmutable;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowSpanEvent;
use Spatie\FlareClient\Report;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Time\Time;
use Spatie\FlareClient\Tracer;

class FakeTime implements Time
{
    protected DateTimeImmutable $dateTime;

    public static function setup(string $dateTime, string $format = 'Y-m-d H:i:s'): self
    {
        $fakeTime = new FakeTime($dateTime, $format);

        Report::useTime($fakeTime);
        GlowSpanEvent::useTime($fakeTime);
        Tracer::useTime($fakeTime);
        Span::useTime($fakeTime);

        return $fakeTime;
    }

    public function __construct(string $dateTime = null, $format = 'Y-m-d H:i:s')
    {
        if (! is_null($dateTime)) {
            $this->setCurrentTime($dateTime, $format);

            return;
        }

        $this->dateTime = new DateTimeImmutable();
    }

    public function getCurrentTime(): int
    {
        return $this->dateTime->getTimestamp() * 1000_000_000; // Nano seconds
    }

    public function setCurrentTime(string $dateTime, $format = 'Y-m-d H:i:s'): void
    {
        $this->dateTime = DateTimeImmutable::createFromFormat($format, $dateTime);
    }
}
