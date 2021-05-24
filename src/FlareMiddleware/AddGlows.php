<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Spatie\FlareClient\Glows\GlowRecorder;
use Spatie\FlareClient\Report;

namespace Spatie\FlareClient\FlareMiddleware;

class AddGlows implements FlareMiddleware
{
    protected GlowRecorder $recorder;

    public function __construct(GlowRecorder $recorder)
    {
        $this->recorder = $recorder;
    }

    public function handle(Report $report, $next)
    {
        foreach ($this->recorder->glows() as $glow) {
            $report->addGlow($glow);
        }

        return $next($report);
    }
}
