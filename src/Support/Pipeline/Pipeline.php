<?php

namespace Spatie\FlareClient\Support\Pipeline;

use Spatie\FlareClient\Report;

class Pipeline
{
    protected Report $report;

    public function sendReport(Report $report)
    {
        $this->report = $report;
    }

    public function through(array $middleware)
    {
        foreach ($this->middleware as $singleMiddleware) {
            $this->handleMiddleware($singleMiddleware);
        }

        return $this->report;
    }

    /**
     * @param string|\Spatie\FlareClient\Support\Pipeline\FlareMiddleware\FlareMiddleware $singleMiddleware
     */
    protected function handleMiddleware($singleMiddleware)
    {
        if (is_string($singleMiddleware)) {
            $singleMiddleware = new $singleMiddleware;
        }

        $singleMiddleware->handle($this->report);
    }
}
