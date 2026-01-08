<?php

namespace Spatie\FlareClient\Truncation;

class TrimPreviousStrategy extends AbstractTruncationStrategy
{
    public function execute(array $payload): array
    {
        $keys = array_keys($payload['previous']);

        foreach (array_reverse($keys) as $key) {
            if (! $this->reportTrimmer->needsToBeTrimmed($payload)) {
                break;
            }

            unset($payload['previous'][$key]);
        }

        return $payload;
    }
}
