<?php

namespace Spatie\FlareClient\Tests\Shared;

use Spatie\FlareClient\Contracts\WithAttributes;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Tests\Shared\Concerns\ExpectAttributes;

class ExpectResource
{
    use ExpectAttributes;

    public function __construct(
        protected Resource $resource
    ) {
    }

    protected function entity(): WithAttributes
    {
        return $this->resource;
    }
}
