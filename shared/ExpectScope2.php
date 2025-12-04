<?php

namespace Spatie\FlareClient\Tests\Shared;

use Spatie\FlareClient\Support\OpenTelemetryAttributeMapper;
use Spatie\FlareClient\Tests\Shared\Concerns\ExpectAttributes2;

class ExpectScope2
{
    use ExpectAttributes2;

    public static function create(array $scope): self
    {
        return new self($scope);
    }

    public function __construct(
        protected array $scope
    ) {
    }

    protected function attributes(): array
    {
        return (new OpenTelemetryAttributeMapper())->attributesToPHP($this->scope['attributes']);
    }
}
