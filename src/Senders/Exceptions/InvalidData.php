<?php

namespace Spatie\FlareClient\Senders\Exceptions;

use Spatie\FlareClient\Senders\Support\Response;

class InvalidData extends BadResponseCode
{
    public static function getMessageForResponse(Response $response): string
    {
        return 'Invalid data found';
    }
}
