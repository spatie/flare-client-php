<?php

namespace Spatie\FlareClient\Support;

class Redactor
{
    /**
     * @param array<string> $censorHeaders
     * @param array<string> $censorBodyFields
     * @param array<string> $censorCookieNames
     */
    public function __construct(
        protected bool $censorClientIps = false,
        protected array $censorHeaders = [],
        protected array $censorBodyFields = [],
        protected bool $censorCookies = false,
        protected bool $censorSession = false,
        protected array $censorCookieNames = [],
    ) {
        $this->censorHeaders = array_map(
            fn (string $header) => $this->normalizeHeaderName($header),
            $this->censorHeaders
        );

        $this->censorBodyFields = array_map(
            fn (string $field) => $this->normalizeBodyFieldName($field),
            $this->censorBodyFields
        );

        $this->censorCookieNames = array_map(
            fn (string $name) => $this->normalizeBodyFieldName($name),
            $this->censorCookieNames
        );
    }

    public function censorHeaders(array $headers): array
    {
        foreach ($headers as $key => $value) {
            $normalizedKey = $this->normalizeHeaderName($key);

            if (in_array($normalizedKey, $this->censorHeaders)) {
                $headers[$key] = $this->censorHeaderValue($value);

                continue;
            }

            if (in_array($normalizedKey, ['cookie', 'set-cookie'])) {
                $headers[$key] = is_array($value)
                    ? array_map(fn (mixed $item) => $this->censorCookieHeaderValue($item), $value)
                    : $this->censorCookieHeaderValue($value);
            }
        }

        return $headers;
    }

    /** @return string|array<int, string> */
    protected function censorHeaderValue(mixed $value): string|array
    {
        if (is_array($value)) {
            return [$this->censorValue(implode(', ', $value))];
        }

        return $this->censorValue($value);
    }

    protected function censorCookieHeaderValue(mixed $value): mixed
    {
        return is_string($value) ? $this->censorCookieHeader($value) : $value;
    }

    public function censorBody(array $body): array
    {
        $anyDepthNames = [];
        $exactPaths = [];

        foreach ($this->censorBodyFields as $field) {
            if (str_contains($field, '.')) {
                $exactPaths[] = explode('.', $field);

                continue;
            }

            $anyDepthNames[] = $field;
        }

        return $this->censor($body, $anyDepthNames, $exactPaths);
    }

    public function censorCookieValues(array $cookies): array
    {
        return $this->censor($cookies, $this->censorCookieNames, []);
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

    protected function censorCookieHeader(string $header): string
    {
        if ($this->censorCookieNames === []) {
            return $header;
        }

        $censored = preg_replace_callback(
            '/(^|[;,]\s*)([^=;,\s]+)=([^;,]*)/',
            function (array $matches): string {
                $name = $this->normalizeBodyFieldName($matches[2]);

                if (! $this->matchesAnyDepthName($this->censorCookieNames, $name)) {
                    return $matches[0];
                }

                return "{$matches[1]}{$matches[2]}=".$this->censorValue($matches[3]);
            },
            $header,
        );

        return $censored ?? $header;
    }

    /**
     * @param array<int, string> $anyDepthNames
     * @param array<int, array<int, string>> $exactPaths
     */
    private function censor(array $data, array $anyDepthNames, array $exactPaths): array
    {
        if ($anyDepthNames === [] && $exactPaths === []) {
            return $data;
        }

        foreach ($data as $key => $value) {
            $normalizedKey = $this->normalizeBodyFieldName((string) $key);

            $shouldCensor = $this->matchesAnyDepthName($anyDepthNames, $normalizedKey)
                || $this->matchesEndOfPath($exactPaths, $normalizedKey);

            if ($shouldCensor) {
                $data[$key] = $this->censorValue($value);

                continue;
            }

            if (! is_array($value)) {
                continue;
            }

            $data[$key] = $this->censor($value, $anyDepthNames, $this->descendPaths($exactPaths, $normalizedKey));
        }

        return $data;
    }

    /** @param array<int, string> $anyDepthNames */
    private function matchesAnyDepthName(array $anyDepthNames, string $key): bool
    {
        foreach ($anyDepthNames as $name) {
            if ($this->segmentMatches($name, $key)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, array<int, string>> $exactPaths */
    private function matchesEndOfPath(array $exactPaths, string $key): bool
    {
        foreach ($exactPaths as $segments) {
            if (count($segments) === 1 && $this->segmentMatches($segments[0], $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<int, string>> $exactPaths
     *
     * @return array<int, array<int, string>>
     */
    private function descendPaths(array $exactPaths, string $key): array
    {
        $descended = [];

        foreach ($exactPaths as $segments) {
            if (count($segments) === 1) {
                continue;
            }

            if (! $this->segmentMatches($segments[0], $key)) {
                continue;
            }

            $descended[] = array_slice($segments, 1);
        }

        return $descended;
    }

    private function segmentMatches(string $segment, string $key): bool
    {
        if (str_ends_with($segment, '*')) {
            return str_starts_with($key, substr($segment, 0, -1));
        }

        return $key === $segment;
    }
}
