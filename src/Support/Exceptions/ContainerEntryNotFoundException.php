<?php

namespace Spatie\FlareClient\Support\Exceptions;

use Psr\Container\NotFoundExceptionInterface;

class ContainerEntryNotFoundException extends \Exception implements NotFoundExceptionInterface
{
    public static function make(string $id): self
    {
        return new self("Entry not found with id: `{$id}`");
    }
}
