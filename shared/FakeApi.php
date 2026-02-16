<?php

namespace Spatie\FlareClient\Tests\Shared;

use Spatie\FlareClient\Api;
use Spatie\FlareClient\ReportFactory;

class FakeApi extends Api
{
    public static array $reports = [];

    public static array $traces = [];

    public static array $logs = [];

    public function report(ReportFactory $report, bool $immediately = false, bool $test = false): array
    {
        return self::$reports[] = $this->exporter->report(
            $report,
        );
    }

    public function trace(array $spans, bool $immediately = false, bool $test = false): array
    {
        return self::$traces[] = $this->exporter->traces(
            $this->resource,
            $this->scope,
            $spans,
        );
    }

    public function log(array $logs, bool $immediately = false, bool $test = false): array
    {
        return self::$logs[] = $this->exporter->logs(
            $this->resource,
            $this->scope,
            $logs,
        );
    }

    public static function assertSent(?int $reports = 0, ?int $traces = 0, ?int $logs = 0): void
    {
        if ($reports !== null) {
            self::assertReportsSent($reports);
        }

        if ($traces !== null) {
            self::assertTracesSent($traces);
        }

        if ($logs !== null) {
            self::assertLogsSent($logs);
        }
    }

    public static function assertReportsSent(int $expectedCount): void
    {
        expect(count(self::$reports))->toBe($expectedCount);
    }

    public static function assertTracesSent(int $expectedCount): void
    {
        expect(count(self::$traces))->toBe($expectedCount);
    }

    public static function assertLogsSent(int $expectedCount): void
    {
        expect(count(self::$logs))->toBe($expectedCount);
    }

    public static function assertNothingSent(): void
    {
        self::assertSent(
            reports: 0,
            traces: 0,
            logs: 0,
        );
    }

    public static function lastReport(): ExpectReport
    {
        return ExpectReport::create(end(self::$reports));
    }

    public static function lastTrace(): ExpectTrace
    {
        return ExpectTrace::create(end(self::$traces));
    }

    public static function lastLog(): ExpectLogData
    {
        return ExpectLogData::create(end(self::$logs));
    }

    public static function reset(): void
    {
        self::$reports = [];
        self::$traces = [];
        self::$logs = [];
    }
}
