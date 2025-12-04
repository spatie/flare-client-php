<?php

namespace Spatie\FlareClient\Tests\Shared;

use Spatie\FlareClient\Contracts\WithAttributes;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Support\OpenTelemetryAttributeMapper;
use Spatie\FlareClient\Tests\Shared\Concerns\ExpectAttributes;
use Spatie\FlareClient\Tests\Shared\Concerns\ExpectAttributes2;

class ExpectResource2
{
    use ExpectAttributes2;

    public static function create(array $resource): self
    {
        return new self($resource);
    }

    public function __construct(
        protected array $resource
    ) {
    }

    protected function attributes(): array
    {
        return (new OpenTelemetryAttributeMapper())->attributesToPHP($this->resource['attributes']);
    }
}
