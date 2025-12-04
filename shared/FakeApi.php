<?php

namespace Spatie\FlareClient\Tests\Shared;

use Closure;
use Spatie\FlareClient\Api;
use Spatie\FlareClient\Enums\FlarePayloadType;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Senders\Support\Response;

class FakeApi extends Api
{
    public static array $reports = [];
    public static array $traces = [];
    public static array $logs = [];

    public function report(array $report, bool $immediately = false): void
    {
        parent::report($report, $immediately);

        self::$reports[] = $report;
    }

    public function trace(array $trace, bool $immediately = false): void
    {
        parent::trace($trace, $immediately);

        self::$traces[] = $trace;
    }

    public function log(array $log, bool $immediately = false): void
    {
        parent::log($log, $immediately);

        self::$logs[] = $log;
    }

    public static function lastLog(): array
    {
        return end(self::$logs);
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

    public static function lastReport(): ExpectReport
    {
        return ExpectReport::create(end(self::$reports));
    }

    public static function lastTrace(): ExpectTrace2
    {
        return ExpectTrace2::create(end(self::$traces));
    }

    public static function reset(): void
    {
        self::$reports = [];
        self::$traces = [];
        self::$logs = [];
    }
}
