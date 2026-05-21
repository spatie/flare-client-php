<?php

namespace Spatie\FlareClient\Contracts;

/**
 * Implemented by attribute providers (route, command, job, ...) that want to
 * expose a small map of attributes for sampling rules to match against,
 * separately from the full attribute payload produced by toArray(). Keep the
 * returned values cheap to compute — this runs on every entry-point handler
 * resolution, including for traces that may still get dropped.
 */
interface SamplingAttributesProvider
{
    /** @return array<string, mixed> */
    public function samplingAttributes(): array;
}
