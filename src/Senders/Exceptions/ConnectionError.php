<?php

namespace Spatie\FlareClient\Senders\Exceptions;

use Exception;
use Spatie\FlareClient\Senders\Support\Response;

class ConnectionError extends Exception
{
    public function __construct(string $error)
    {
        parent::__construct("Could not perform request because: {$error}");
    }
}
