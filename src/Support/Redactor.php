<?php

namespace Spatie\FlareClient\Support;

class Redactor
{
    /**
     * @param array<string> $censorHeaders
     * @param array<string> $censorBodyFields
     */
    public function __construct(
        protected bool $censorClientIps = false,
        protected array $censorHeaders = [],
        protected array $censorBodyFields = [],
    ) {
        $this->censorHeaders = array_map(
            fn (string $header) => $this->normalizeHeaderName($header),
            $this->censorHeaders
        );

        $this->censorBodyFields = array_map(
            fn (string $field) => $this->normalizeBodyFieldName($field),
            $this->censorBodyFields
        );
    }

    public function censorHeaders(array $headers): array
    {
        foreach ($headers as $key => $value) {
            if (in_array($this->normalizeHeaderName($key), $this->censorHeaders)) {
                $headers[$key] = $this->censorValue($value);
            }
        }

        return $headers;
    }

    public function censorBody(array $body): array
    {
        foreach ($body as $key => $value) {
            if (in_array($this->normalizeBodyFieldName($key), $this->censorBodyFields)) {
                $body[$key] = $this->censorValue($value);
            }
        }

        return $body;
    }

    public function shouldCensorClientIps(): bool
    {
        return $this->censorClientIps;
    }

    public function normalizeHeaderName(string $name): string
    {
        // Using Symfony's convention
        return strtr(
            $name,
            '_ABCDEFGHIJKLMNOPQRSTUVWXYZ', // HeaderBag::UPPER
            '-abcdefghijklmnopqrstuvwxyz' // HeaderBag::LOWER
        );
    }

    public function normalizeBodyFieldName(string $name): string
    {
        return mb_strtolower($name);
    }

    protected function censorValue(mixed $value): string
    {
        return '<CENSORED:'.get_debug_type($value).'>';
    }
}
