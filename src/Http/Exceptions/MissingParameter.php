<?php

namespace Spatie\FlareClient\Http\Exceptions;

use Exception;

class MissingParameter extends Exception
{
    public static function create(string $parameterName): self
    {
        return new static("`$parameterName` is a required parameter");
    }
}
