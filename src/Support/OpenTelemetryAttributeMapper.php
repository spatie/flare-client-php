<?php

namespace Spatie\FlareClient\Support;

class OpenTelemetryAttributeMapper
{
    public function attributes(array $attributes): array
    {
        return array_map(function ($value) {
            return $this->value($value);
        }, $attributes);
    }

    public function value(mixed $value): array
    {
        if (is_string($value)) {
            return ['stringValue' => $value];
        }

        if(is_bool($value)){
            return ['boolValue' => $value];
        }

        if(is_int($value)){
            return ['intValue' => $value];
        }

        if(is_float($value)){
            return ['doubleValue' => $value];
        }

        if(is_array($value) && array_is_list($value)){
            return ['arrayValue' => $value];
        }

        if(is_array($value) && ! array_is_list($value)){
            return ['kvlistValue' => $value];
        }

        return $value;
    }
}
