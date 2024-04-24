<?php

namespace Spatie\FlareClient\Tests\TestClasses;

class OutOfMemory
{
    public static function execute(): void
    {
        ini_set('memory_limit', '10M');

        self::consumeMemory(10 * 1024 * 1024); // 10 MB
    }

    private static function consumeMemory(int $size): void
    {
        str_repeat('0', $size);

        self::consumeMemory($size);
    }
}
