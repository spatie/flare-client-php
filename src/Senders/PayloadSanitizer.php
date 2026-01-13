<?php

namespace Spatie\FlareClient\Senders;

interface PayloadSanitizer
{
    /**
     * @template K of array-key
     *
     * @param  array<K,mixed>  $payload
     * @return array<K,mixed>
     */
    public function sanitize(array $payload): array;
}
