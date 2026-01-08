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
        protected bool $censorCookies = false,
        protected bool $censorSession = false,
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
        foreach ($this->censorBodyFields as $censor) {
            $body = $this->censorByPath($body, explode('.', $censor));
        }

        return $body;
    }

    public function shouldCensorClientIps(): bool
    {
        return $this->censorClientIps;
    }

    public function shouldCensorCookies(): bool
    {
        return $this->censorCookies;
    }

    public function shouldCensorSession(): bool
    {
        return $this->censorSession;
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

    private function censorByPath(array $data, array $segments): array
    {
        if (empty($segments)) {
            return $data;
        }

        $currentSegment = $segments[0];
        $remainingSegments = array_slice($segments, 1);

        foreach ($data as $key => $value) {
            $normalizedKey = $this->normalizeBodyFieldName((string) $key);

            $matches = ($currentSegment === '*') || ($normalizedKey === $currentSegment);

            if (! $matches) {
                continue;
            }

            if (empty($remainingSegments)) {
                $data[$key] = $this->censorValue($value);

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->censorByPath($value, $remainingSegments);
            }
        }

        return $data;
    }
}
