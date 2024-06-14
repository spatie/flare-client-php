<?php

namespace Spatie\FlareClient\Performance\Resources;

use Spatie\FlareClient\Performance\Concerns\HasAttributes;
use Spatie\FlareClient\Performance\Support\GitAttributesProvider;
use Spatie\FlareClient\Performance\Support\Telemetry;

class Resource
{
    use HasAttributes;

    /**
     * @param array $attributes <string, mixed>
     */
    public function __construct(array $attributes)
    {
        $this->setAttributes($attributes);
    }

    public static function build(
        string $serviceName,
        ?string $serviceVersion,
        string $telemetrySdkName = Telemetry::NAME,
        string $telemetrySdkVersion = Telemetry::VERSION,
    ): self {
        return new self([
            'service.name' => $serviceName,
            'service.version' => $serviceVersion,
            'telemetry.sdk.language' => 'php',
            'telemetry.sdk.name' => $telemetrySdkName,
            'telemetry.sdk.version' => $telemetrySdkVersion,
        ]);
    }

    public function host(): self
    {
        $this->attributes['host.ip'] = gethostbyname(gethostname());
        $this->attributes['host.name'] = gethostname();

        return $this;
    }

    public function git(): self
    {
        $attributes = (new GitAttributesProvider())->getAttributes();

        if (empty($attributes)) {
            return $this;
        }

        $this->addAttributes($attributes);

        if ($this->attributes['service.version'] === null && isset($attributes['git.tag'])) {
            $this->attributes['service.version'] = $attributes['git.tag'];
        }

        if ($this->attributes['service.version'] === null && isset($attributes['git.hash'])) {
            $this->attributes['service.version'] = $attributes['git.hash'];
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'attributes' => $this->attributesToArray(),
            'droppedAttributesCount' => $this->droppedAttributesCount,
        ];
    }
}
