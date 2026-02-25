<?php

namespace Spatie\FlareClient\Senders\Exceptions;

use Exception;

class DaemonTimeoutException extends Exception
{
    public function __construct(int $seconds)
    {
        parent::__construct("Timed out waiting for daemon test response after {$seconds} seconds");
    }
}
