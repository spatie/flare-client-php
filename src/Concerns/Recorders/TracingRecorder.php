<?php

namespace Spatie\FlareClient\Concerns\Recorders;

trait TracingRecorder
{
    protected bool $withTraces = false;

    private function configureTracing(array $config): void
    {
        $this->withTraces = $config['with_traces'] ?? false;
    }
}
