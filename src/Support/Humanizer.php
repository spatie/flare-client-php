<?php

namespace Spatie\FlareClient\Support;

use Throwable;

class Humanizer
{
    public static function contentSize(mixed $contents): string
    {
        $bytes = static::getSizeOfContents($contents);

        if ($bytes === null) {
            return 'unknown';
        }

        return self::filesize($bytes);
    }

    public static function filesize(?int $bytes): string
    {
        if ($bytes === null) {
            return '?';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public static function unixTime(int $unixTime): string
    {
        return date('Y-m-d H:i:s', $unixTime);
    }

    public static function filesystemPaths(array|string|null $paths, string $type = 'paths'): string
    {
        if (is_string($paths)) {
            return $paths;
        }

        if (is_null($paths)) {
            return '/';
        }

        $paths = array_map(fn ($path) => $path === null ? '/' : $path, $paths);

        $count = count($paths);

        if ($count === 1) {
            return $paths[0];
        }

        if ($count <= 3) {
            return implode(', ', $paths);
        }

        $firstThreePaths = array_slice($paths, 0, 3);
        $remainingCount = $count - 3;

        return implode(', ', $firstThreePaths)." and +{$remainingCount} {$type}";
    }

    protected static function getSizeOfContents(
        mixed $contents
    ): ?int {
        if ($contents === null) {
            return null;
        }

        if (is_string($contents)) {
            return strlen($contents);
        }

        if (is_resource($contents)) {
            return null;
        }

        if (is_array($contents)) {
            try {
                static::getSizeOfContents(json_encode($contents, flags: JSON_THROW_ON_ERROR));
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }
}
