<?php

namespace Spatie\FlareClient\Senders\Support;

use JsonException;
use Spatie\FlareClient\Senders\PayloadSanitizer;

class JsonEncodableSanitizer implements PayloadSanitizer
{
    /**
     * A prefix ensuing replaced values in sanitized payloads remain identifiable
     */
    const SANITIZED_PAYLOAD_ENTRY_REPLACEMENT_PREFIX = 'Spatie/Flare';

    public function sanitize(array $payload): array
    {
        foreach ($payload as $key => $value) {
            try {
                json_encode($payload[$key], JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                if (is_array($value)) {
                    $payload[$key] = $this->sanitize($payload[$key]);

                    continue;
                }

                $payload[$key] = $this->formattedReplacementMessage($value, $e->getMessage());
            }

        }

        return $payload;
    }

    protected function formattedReplacementMessage(mixed $value, string $errorMessage): string
    {
        $valueInfo = is_string($value) ? strlen($value).' bytes' : gettype($value);

        return sprintf(
            '[%s Failed to encode]: %s - %s',
            static::SANITIZED_PAYLOAD_ENTRY_REPLACEMENT_PREFIX,
            $valueInfo,
            $errorMessage
        );
    }
}
