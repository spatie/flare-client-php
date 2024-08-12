<?php

namespace Spatie\FlareClient\Recorders\ExceptionRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpanEvents;
use Spatie\FlareClient\Contracts\Recorders\Recorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Report;

class ExceptionRecorder implements Recorder
{
    use RecordsSpanEvents;

    public static function type(): string|RecorderType
    {
        return RecorderType::Exception;
    }

    public function record(Report $report): void
    {
        $this->persistEntry(fn () => ExceptionSpanEvent::fromFlareReport($report));
    }
}
