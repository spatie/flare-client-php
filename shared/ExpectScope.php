<?php

namespace Spatie\FlareClient\Tests\Shared;

use Spatie\FlareClient\Support\OpenTelemetryAttributeMapper;
use Spatie\FlareClient\Tests\Shared\Concerns\ExpectAttributes;

class ExpectScope
{
    use ExpectAttributes;

    public static function create(array $scope): self
    {
        return new self($scope);
    }

    public function __construct(
        protected array $scope
    ) {
    }

    public function attributes(): array
    {
        return (new OpenTelemetryAttributeMapper())->attributesToPHP($this->scope['attributes']);
    }
}
