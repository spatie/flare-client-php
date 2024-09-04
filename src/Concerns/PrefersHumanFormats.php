<?php

namespace Spatie\FlareClient\Concerns;

trait PrefersHumanFormats
{
    public function humanFilesize(?int $bytes): string
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

    public function humanizeUnixTime(int $unixTime): string
    {
        return date('Y-m-d H:i:s', $unixTime);
    }
}
