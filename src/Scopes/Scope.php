<?php

namespace Spatie\FlareClient\Scopes;

use Spatie\FlareClient\Concerns\HasAttributes;
use Spatie\FlareClient\Contracts\WithAttributes;
use Spatie\FlareClient\Support\Telemetry;

class Scope implements WithAttributes
{
    use HasAttributes;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public string $name = Telemetry::NAME,
        public string $version = Telemetry::VERSION,
        array $attributes = [],
    ) {
        $this->addAttributes($attributes);
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function version(string $version): self
    {
        $this->version = $version;

        return $this;
    }
}
