<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Spans\Span;

class AddJobInformation implements FlareMiddleware
{
    public static ?string $usedTrackingUuid = null;

    public static ?Span $latestJob = null;

    public static ?EntryPoint $entryPoint = null;

    public function handle(ReportFactory $report, Closure $next): ReportFactory
    {
        if ($entryPoint = static::$entryPoint) {
            $report->addAttributes($entryPoint->toAttributes());

            static::$entryPoint = null;
        }

        if ($latestJob = static::$latestJob) {
            $report->span($latestJob);

            static::$latestJob = null;
        }

        if (static::$usedTrackingUuid) {
            $report->trackingUuid(static::$usedTrackingUuid);

            static::$usedTrackingUuid = null;
        }

        return $next($report);
    }

    public static function clearLatestJobInfo(): void
    {
        self::$latestJob = null;
        self::$usedTrackingUuid = null;
        self::$entryPoint = null;
    }

    public static function setLatestJob(
        Span $job,
    ): void {
        self::$latestJob = $job;
    }

    public static function setUsedTrackingUuid(
        string $uuid,
    ): void {
        self::$usedTrackingUuid = $uuid;
    }

    public static function setEntryPoint(
        EntryPoint $entryPoint,
    ): void {
        self::$entryPoint = $entryPoint;
    }
}
