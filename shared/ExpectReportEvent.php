<?php

namespace Spatie\FlareClient\Tests\Shared;

use DateTimeInterface;
use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Tests\Shared\Concerns\ExpectAttributes;
use Spatie\FlareClient\Time\TimeHelper;

class ExpectReportEvent
{
    use ExpectAttributes;

    public function __construct(
        public array $event
    ) {
    }

    public function expectType(
        FlareSpanType|FlareSpanEventType $type
    ): self {
        $type = $this->attributes()['flare.span_type'];

        if(is_object($type)) {
            $type = $type->value;
        }

        expect($type)->toEqual($type->value);

        return $this;
    }

    public function expectStart(
        int|DateTimeInterface $start
    ): self {
        $expectedStart = $start instanceof DateTimeInterface ? TimeHelper::dateTimeToNano($start) : $start;

        expect($this->event['startTimeUnixNano'])->toBe($expectedStart);

        return $this;
    }

    public function expectEnd(
        int|DateTimeInterface|null $end
    ): self {
        $expectedEnd = $end instanceof DateTimeInterface ? TimeHelper::dateTimeToNano($end) : $end;

        expect($this->event['endTimeUnixNano'])->toBe($expectedEnd);

        return $this;
    }

    public function expectMissingEnd(): self
    {
        return $this->expectEnd(null);
    }

    public function attributes(): array
    {
        return $this->event['attributes'];
    }
}
