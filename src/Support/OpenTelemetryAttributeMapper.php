<?php

namespace Spatie\FlareClient\Support;

use BackedEnum;

class OpenTelemetryAttributeMapper
{
    public function attributesToOpenTelemetry(array $attributes): array
    {
        return array_values(array_filter(array_map(function ($value, $key) {
            $mapped = $this->valueToOpenTelemetry($value);

            if ($mapped === null) {
                return null;
            }

            return [
                'key' => $key,
                'value' => $mapped,
            ];
        }, $attributes, array_keys($attributes))));
    }

    public function valueToOpenTelemetry(mixed $value): ?array
    {
        if (is_string($value)) {
            return ['stringValue' => $value];
        }

        if (is_bool($value)) {
            return ['boolValue' => $value];
        }

        if (is_int($value)) {
            return ['intValue' => $value];
        }

        if (is_float($value)) {
            return ['doubleValue' => $value];
        }

        if (is_array($value) && ! array_is_list($value)) {
            return [
                'kvlistValue' => [
                    'values' => array_map(fn ($item, $key) => [
                        'key' => $key,
                        'value' => $this->valueToOpenTelemetry($item),
                    ], $value, array_keys($value)),
                ],
            ];
        }

        if (is_array($value) && array_is_list($value)) {
            return [
                'arrayValue' => [
                    'values' => array_values(array_map(
                        fn (mixed $item) => $this->valueToOpenTelemetry($item),
                        $value
                    )),
                ],
            ];
        }

        if ($value === null) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return $this->valueToOpenTelemetry($value->value);
        }

        return ['stringValue' => json_encode($value)];
    }

    public function attributesToPHP(array $attributes): array
    {
        $out = [];

        foreach ($attributes as $attribute) {
            $value = $this->valueToPHP($attribute['value']);

            if ($value === null) {
                continue;
            }

            $out[$attribute['key']] = $value;
        }

        return $out;
    }

    public function valueToPHP(array $value): mixed
    {
        if (array_key_exists('stringValue', $value)) {
            if (str_starts_with($value['stringValue'], '{') && str_ends_with($value['stringValue'], '}')) {
                try {
                    return json_decode($value['stringValue'], true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    return $value['stringValue'];
                }
            }

            return $value['stringValue'];
        }

        if (array_key_exists('boolValue', $value)) {
            return $value['boolValue'];
        }

        if (array_key_exists('intValue', $value)) {
            return $value['intValue'];
        }

        if (array_key_exists('doubleValue', $value)) {
            return $value['doubleValue'];
        }

        if (array_key_exists('kvlistValue', $value)) {
            $out = [];

            foreach ($value['kvlistValue']['values'] as $item) {
                $out[$item['key']] = $this->valueToPHP($item['value']);
            }

            return $out;
        }

        if (array_key_exists('arrayValue', $value)) {
            return array_map(
                fn ($item) => $this->valueToPHP($item),
                $value['arrayValue']['values']
            );
        }

        return null;
    }
}
