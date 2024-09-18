<?php

namespace Spatie\FlareClient\Senders\Support;

class Response
{
    public function __construct(
        public readonly int $code,
        public readonly mixed $body,
    ) {
    }
}
