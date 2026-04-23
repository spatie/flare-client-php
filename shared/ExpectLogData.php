<?php

namespace Spatie\FlareClient\Tests\Shared;

class ExpectLogData
{
    /** @var array<ExpectLog> */
    public array $expectLogs;

    public static function create(array $logData): self
    {
        return new self($logData);
    }

    public function __construct(
        public array $logData
    ) {
        $this->expectLogs = array_map(
            fn (array $log) => new ExpectLog($log),
            $this->logData['resourceLogs'][0]['scopeLogs'][0]['logRecords'],
        );
    }

    public function expectLog(int $index): ExpectLog
    {
        return $this->expectLogs[$index];
    }

    public function expectLogCount(int $count): self
    {
        expect($this->expectLogs)->toHaveCount($count);

        return $this;
    }

    public function expectNoLogs(): self
    {
        expect($this->expectLogs)->toBeEmpty();

        return $this;
    }

    public function expectResource(): ExpectResource
    {
        return new ExpectResource($this->logData['resourceLogs'][0]['resource']);
    }

    public function expectScope(): ExpectScope
    {
        return ExpectScope::create($this->logData['resourceLogs'][0]['scopeLogs'][0]['scope']);
    }

    public function toArray(): array
    {
        return $this->logData;
    }

    public function toString(): string
    {
        return implode(PHP_EOL, array_map(fn(ExpectLog $log) => $log->toString(), $this->expectLogs));
    }
}
