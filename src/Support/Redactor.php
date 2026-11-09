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
                $headers[$key] = $this->censorValue($value);

                continue;
            }

            if (in_array($normalizedKey, ['cookie', 'set-cookie']) && is_string($value)) {
                $headers[$key] = $this->censorCookieHeader($value);
            }
        }

        return $headers;
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
        return $this->censor($cookies, $this->censorCookieNames, [], splitKeys: false);
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

                if (! $this->matchesAnyDepthName($this->censorCookieNames, [$name])) {
                    return $matches[0];
                }

                return "{$matches[1]}{$matches[2]}=".$this->censorValue($matches[3]);
            },
            $header,
        );

        return $censored ?? $header;
    }

    /**
     * A body key can be a dotted path itself, for example `form.password` in a Livewire
     * update or an HTML form. It is split into segments and matched as if it were nested.
     * Cookie names are never split, a dot is a regular character there.
     *
     * @param array<int, string> $anyDepthNames
     * @param array<int, array<int, string>> $exactPaths
     */
    private function censor(array $data, array $anyDepthNames, array $exactPaths, bool $splitKeys = true): array
    {
        if ($anyDepthNames === [] && $exactPaths === []) {
            return $data;
        }

        foreach ($data as $key => $value) {
            $normalizedKey = $this->normalizeBodyFieldName((string) $key);

            $keySegments = $splitKeys
                ? explode('.', $normalizedKey)
                : [$normalizedKey];

            $shouldCensor = $this->matchesAnyDepthName($anyDepthNames, $keySegments)
                || $this->matchesEndOfPath($exactPaths, $keySegments);

            if ($shouldCensor) {
                $data[$key] = $this->censorValue($value);

                continue;
            }

            if (! is_array($value)) {
                continue;
            }

            $data[$key] = $this->censor($value, $anyDepthNames, $this->descendPaths($exactPaths, $keySegments), $splitKeys);
        }

        return $data;
    }

    /**
     * @param array<int, string> $anyDepthNames
     * @param array<int, string> $keySegments
     */
    private function matchesAnyDepthName(array $anyDepthNames, array $keySegments): bool
    {
        foreach ($anyDepthNames as $name) {
            foreach ($keySegments as $keySegment) {
                if ($this->segmentMatches($name, $keySegment)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<int, array<int, string>> $exactPaths
     * @param array<int, string> $keySegments
     */
    private function matchesEndOfPath(array $exactPaths, array $keySegments): bool
    {
        foreach ($exactPaths as $pathSegments) {
            if (count($pathSegments) !== count($keySegments)) {
                continue;
            }

            if ($this->segmentsMatch($pathSegments, $keySegments)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<int, string>> $exactPaths
     * @param array<int, string> $keySegments
     *
     * @return array<int, array<int, string>>
     */
    private function descendPaths(array $exactPaths, array $keySegments): array
    {
        $descended = [];

        foreach ($exactPaths as $pathSegments) {
            if (count($pathSegments) <= count($keySegments)) {
                continue;
            }

            if (! $this->segmentsMatch(array_slice($pathSegments, 0, count($keySegments)), $keySegments)) {
                continue;
            }

            $descended[] = array_slice($pathSegments, count($keySegments));
        }

        return $descended;
    }

    /**
     * @param array<int, string> $patternSegments
     * @param array<int, string> $keySegments
     */
    private function segmentsMatch(array $patternSegments, array $keySegments): bool
    {
        foreach ($patternSegments as $index => $patternSegment) {
            if (! $this->segmentMatches($patternSegment, $keySegments[$index])) {
                return false;
            }
        }

        return true;
    }

    private function segmentMatches(string $segment, string $key): bool
    {
        if (str_ends_with($segment, '*')) {
            return str_starts_with($key, substr($segment, 0, -1));
        }

        return $key === $segment;
    }
}
