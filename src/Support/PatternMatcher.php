<?php

namespace Spatie\FlareClient\Support;

class PatternMatcher
{
    public static function matches(string $value, string $pattern): bool
    {
        $regex = '/^'.str_replace('\\*', '.*', preg_quote($pattern, '/')).'$/';

        return (bool) preg_match($regex, $value);
    }

    /** @param array<int, string> $patterns */
    public static function matchesAny(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (self::matches($value, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public static function regex(string $value, string $pattern): bool
    {
        return @preg_match($pattern, $value) === 1;
    }

    /** @param array<int, string> $patterns */
    public static function regexAny(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (self::regex($value, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
