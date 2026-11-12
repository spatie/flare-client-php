<?php

namespace Spatie\FlareClient\Support;

class HttpHeaderAttributes
{
    /**
     * @param array<string, string|array<int, string|null>> $headers
     *
     * @return array<string, array<int, string>>
     */
    public static function request(array $headers, Redactor $redactor): array
    {
        return static::toAttributes('http.request.header', $headers, $redactor);
    }

    /**
     * @param array<string, string|array<int, string|null>> $headers
     *
     * @return array<string, array<int, string>>
     */
    public static function response(array $headers, Redactor $redactor): array
    {
        return static::toAttributes('http.response.header', $headers, $redactor);
    }

    /**
     * @param array<string, string|array<int, string|null>> $headers
     *
     * @return array<string, array<int, string>>
     */
    protected static function toAttributes(string $prefix, array $headers, Redactor $redactor): array
    {
        $headers = array_filter(array_map(
            fn (array|string $values) => static::normalizeValues($values),
            $headers,
        ));

        $attributes = [];

        foreach ($redactor->censorHeaders($headers) as $name => $values) {
            $key = "{$prefix}.".strtolower((string) $name);

            $attributes[$key] = [...($attributes[$key] ?? []), ...$values];
        }

        return $attributes;
    }

    /**
     * @param string|array<int, string|null> $values
     *
     * @return array<int, string>
     */
    protected static function normalizeValues(array|string $values): array
    {
        $values = array_map(fn (mixed $value) => (string) $value, (array) $values);

        return array_values(array_filter($values, fn (string $value) => $value !== ''));
    }
}
