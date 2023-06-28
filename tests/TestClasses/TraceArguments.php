<?php

namespace Spatie\FlareClient\Tests\TestClasses;

use DateTime;
use Exception;

class TraceArguments
{
    public static function create(): self
    {
        return new self();
    }

    public function exception(
        string $string,
        DateTime $dateTime,
    ): Exception {
        return new Exception('Some exception');
    }
}
