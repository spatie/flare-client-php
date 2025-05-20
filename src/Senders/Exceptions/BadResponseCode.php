<?php

namespace Spatie\FlareClient\Senders\Exceptions;

use Exception;
use Spatie\FlareClient\Senders\Support\Response;

class BadResponseCode extends Exception
{
    /**
     * @var array<int, mixed>
     */
    public array $errors = [];

    public function __construct(public Response $response)
    {
        parent::__construct(static::getMessageForResponse($response));

        $this->errors = $response->body['errors'] ?? [];
    }

    public static function getMessageForResponse(Response $response): string
    {
        return "Response code {$response->code} returned";
    }
}
