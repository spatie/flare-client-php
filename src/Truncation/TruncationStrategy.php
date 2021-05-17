<?php

namespace Spatie\FlareClient\Truncation;

interface TruncationStrategy
{
    public function execute(array $payload): array;
}
