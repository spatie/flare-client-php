<?php

namespace Spatie\FlareClient\Tests\Shared;

use DateTimeInterface;
use Spatie\FlareClient\Support\OpenTelemetryAttributeMapper;
use Spatie\FlareClient\Tests\Shared\Concerns\ExpectAttributes;
use Spatie\FlareClient\Time\TimeHelper;

class ExpectLog
{
    use ExpectAttributes;

    public static function create(array $log): self
    {
        return new self($log);
    }

    public function __construct(
        public array $log
    ) {
    }

    public function expectBody(mixed $body): self
    {
        expect($this->log['body'])->toBe($body);

        return $this;
    }

    public function expectTime(DateTimeInterface|int $timeUnixNano): self
    {
        $expectedTime = $timeUnixNano instanceof DateTimeInterface
            ? TimeHelper::dateTimeToNano($timeUnixNano)
            : $timeUnixNano;

        expect($this->log['timeUnixNano'])->toEqual($expectedTime);

        return $this;
    }

    public function expectObservedTime(DateTimeInterface|int $observedTimeUnixNano): self
    {
        $expectedTime = $observedTimeUnixNano instanceof DateTimeInterface
            ? TimeHelper::dateTimeToNano($observedTimeUnixNano)
            : $observedTimeUnixNano;

        expect($this->log['observedTimeUnixNano'])->toEqual($expectedTime);

        return $this;
    }

    public function expectTraceId(string $traceId): self
    {
        expect($this->log['traceId'])->toBe($traceId);

        return $this;
    }

    public function expectSpanId(string $spanId): self
    {
        expect($this->log['spanId'])->toBe($spanId);

        return $this;
    }

    public function expectFlags(string $flags): self
    {
        expect($this->log['flags'])->toBe($flags);

        return $this;
    }

    public function expectSampling(): self
    {
        expect($this->log['flags'])->toBe('01');
    }

    public function expectNotSampling(): self
    {
        expect($this->log['flags'])->toBe('00');
    }

    public function expectSeverityText(string $severityText): self
    {
        expect($this->log['severityText'])->toBe($severityText);

        return $this;
    }

    public function expectSeverityNumber(int $severityNumber): self
    {
        expect($this->log['severityNumber'])->toBe($severityNumber);

        return $this;
    }

    protected function attributes(): array
    {
        if (! array_key_exists('attributes', $this->log)) {
            return [];
        }

        return (new OpenTelemetryAttributeMapper)->attributesToPHP($this->log['attributes']);
    }
}
