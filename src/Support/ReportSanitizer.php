<?php

namespace Spatie\FlareClient\Support;

use JsonException;

class ReportSanitizer
{
    // A suffix for replaced entries ensuring they are recognizable
    const REPLACED_ENTRY_PREFIX = 'Spatie/Flare';

    /**
     * @template K of array-key
     * @template V
     *
     * @param  array<K,V>  $payload
     * @return array<K,mixed>
     */
    public static function sanitizePayload(array $payload): array
    {
        try {
            json_encode($payload, JSON_THROW_ON_ERROR);

            return $payload;
        } catch (JsonException $e) {
            return self::replaceNonEncodableEntries($payload);
        }
    }

    /**
     * @template K of array-key
     *
     * @param  array<K,mixed>  $input
     * @return array<K,mixed>
     */
    protected static function replaceNonEncodableEntries(array $input): array
    {
        foreach ($input as $key => $value) {
            try {
                json_encode($input[$key], JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                if (is_string($value)) {
                    $input[$key] = sprintf('[%s: CORRUPT DATA]: %d bytes | ERR: %s', self::REPLACED_ENTRY_PREFIX, strlen($value), $e->getMessage());
                } elseif (is_array($value)) {
                    $input[$key] = self::replaceNonEncodableEntries($value);
                } else {
                    $input[$key] = sprintf('[%s: NON ENCODABLE]: type %s', self::REPLACED_ENTRY_PREFIX, gettype($value));
                }
            }
        }

        return $input;
    }
}
