<?php

namespace Spatie\FlareClient\Senders\Exceptions;

use Spatie\FlareClient\Senders\Support\Response;

class NotFound extends BadResponseCode
{
    public static function getMessageForResponse(Response $response): string
    {
        return 'Not found';
    }
}
