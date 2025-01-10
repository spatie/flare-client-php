<?php

namespace Spatie\FlareClient\Tests\Shared;

use Spatie\FlareClient\Contracts\WithAttributes;
use Spatie\FlareClient\Scopes\Scope;

class ExpectScope
{
    use Concerns\ExpectAttributes;

    public function __construct(
        protected Scope $scope
    ) {
    }

    public function hasName(string $name): self
    {
        expect($this->scope->name)->toEqual($name);

        return $this;
    }

    public function hasVersion(string $version): self
    {
        expect($this->scope->version)->toEqual($version);

        return $this;
    }

    protected function entity(): WithAttributes
    {
        return $this->scope;
    }
}
