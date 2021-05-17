<?php

namespace Spatie\FlareClient\Truncation;

abstract class AbstractTruncationStrategy implements TruncationStrategy
{
    /** @var ReportTrimmer */
    protected $reportTrimmer;

    public function __construct(ReportTrimmer $reportTrimmer)
    {
        $this->reportTrimmer = $reportTrimmer;
    }
}
