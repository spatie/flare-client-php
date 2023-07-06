<?php

namespace Spatie\FlareClient\Tests\TestClasses;

use Closure;
use ErrorException;
use Spatie\FlareClient\Context\ConsoleContextProvider;
use Spatie\FlareClient\Report;

class FakeErrorHandler
{
    public static function setup(Closure $assertions)
    {
        set_error_handler(function ($severity, $message, $file, $line) use ($assertions) {
            $throwable =  new ErrorException($message, 0, $severity, $file, $line);

            $assertions($throwable);
        });
    }
}
