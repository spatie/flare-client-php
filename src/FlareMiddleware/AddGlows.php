<?php

namespace Spatie\FlareClient\FlareMiddleware;

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Spatie\FlareClient\Report;

class AddGlows implements FlareMiddleware
{
    protected GlowRecorder $recorder;

    public function __construct(GlowRecorder $recorder)
    {
        $this->recorder = $recorder;
    }

    public function handle(Report $report, Closure $next)
    {
        $report->setGlows($this->recorder->getGlows());

        return $next($report);
    }
}
