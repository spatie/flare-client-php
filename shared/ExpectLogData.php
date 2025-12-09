<?php

namespace Spatie\FlareClient\Tests\Shared;

class ExpectLogData
{
    public static function create(array $logData): self
    {
        return new self($logData);
    }

    public function __construct(
        public array $logData
    ) {
    }

    public function expectLog(int $index): ExpectLog
    {
        return new ExpectLog($this->logData['resourceLogs'][0]['scopeLogs'][0]['logRecords'][$index]);
    }

    public function expectLogCount(int $count): self
    {
        expect($this->logData['resourceLogs'][0]['scopeLogs'][0]['logRecords'])->toHaveCount($count);

        return $this;
    }

    public function expectNoLogs(): self
    {
        expect($this->logData['resourceLogs'][0]['scopeLogs'][0]['logRecords'])->toBeEmpty();

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
}