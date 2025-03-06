<?php

namespace Spatie\FlareClient\Support;

class Ids
{
    const FLARE_TRACE_PARENT = '_flare_trace_parent';

    public function trace(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function span(): string
    {
        return bin2hex(random_bytes(8));
    }

    public function uuid(): string
    {
        // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
        $data = random_bytes(16);

        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        // Output the 36 character UUID.
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function traceParent(
        string $traceId,
        string $parentSpanId,
        bool $sampling,
    ): string {
        $traceState = $sampling ? '01' : '00';

        return "00-{$traceId}-{$parentSpanId}-{$traceState}";
    }

    /**
     * @return ?array{traceId: string, parentSpanId: string, sampling: bool}
     */
    public function parseTraceParent(
        string $traceParent
    ): ?array {
        $parts = explode('-', $traceParent);

        if (count($parts) !== 4) {
            return null;
        }

        if ($parts[0] !== '00') {
            return null;
        }

        return [
            'traceId' => $parts[1],
            'parentSpanId' => $parts[2],
            'sampling' => $parts[3] === '01',
        ];
    }
}
