<?php

namespace Spatie\FlareClient\Middleware;

use Spatie\FlareClient\Glows\Recorder;
use Spatie\FlareClient\Report;

class AddGlows
{
    private Recorder $recorder;

    public function __construct(Recorder $recorder)
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
