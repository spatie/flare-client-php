<?php

namespace Spatie\FlareClient\Resources;

use Composer\InstalledVersions;
use Spatie\FlareClient\AttributesProviders\GitAttributesProvider;
use Spatie\FlareClient\Concerns\HasAttributes;
use Spatie\FlareClient\Contracts\WithAttributes;

/** @see https://github.com/opentelemetry-php/sdk/blob/main/Resource/Detectors/ */
class Resource implements WithAttributes
{
    use HasAttributes;

    /**
     * @param array $attributes <string, mixed>
     */
    public function __construct(
        protected string $serviceName,
        protected ?string $serviceVersion,
        protected ?string $serviceStage,
        protected string $telemetrySdkName,
        protected string $telemetrySdkVersion,
        array $attributes = []
    ) {
        $this->attributes = $attributes;
    }

    public function serviceName(string $name): self
    {
        $this->serviceName = $name;

        return $this;
    }

    public function serviceVersion(?string $version): self
    {
        $this->serviceVersion = $version;

        return $this;
    }

    public function serviceStage(?string $stage): self
    {
        $this->serviceStage = $stage;

        return $this;
    }

    public function composer(): self
    {
        $this->serviceName = InstalledVersions::getRootPackage()['name'];
        $this->serviceVersion = InstalledVersions::getRootPackage()['pretty_version'];

        return $this;
    }

    public function host(): self
    {
        $this->attributes['host.ip'] = gethostbyname(gethostname());
        $this->attributes['host.name'] = php_uname('n');
        $this->attributes['host.arch'] = php_uname('m');

        return $this;
    }

    public function operatingSystem(): self
    {
        $this->attributes['os.type'] = strtolower(PHP_OS_FAMILY);
        $this->attributes['os.description'] = php_uname('r');
        $this->attributes['os.name'] = PHP_OS;
        $this->attributes['os.version'] = php_uname('v');

        return $this;
    }

    public function process(): self
    {
        $this->attributes['process.pid'] = getmypid();
        $this->attributes['process.executable.path'] = PHP_BINARY;

        if ($_SERVER['argv'] ?? null) {
            $this->attributes['process.command'] = $_SERVER['argv'][0];
            $this->attributes['process.command.args'] = $_SERVER['argv'];
        }

        if (extension_loaded('posix') && ($user = \posix_getpwuid(\posix_geteuid())) !== false) {
            $this->attributes['process.owner'] = $user['name'];
        }

        return $this;
    }

    public function processRuntime(): self
    {
        $this->attributes['process.runtime.name'] = "PHP (".php_sapi_name().")";
        $this->attributes['process.runtime.version'] = PHP_VERSION;

        return $this;
    }

    public function git(): self
    {
        $attributes = (new GitAttributesProvider())->toArray();

        if (empty($attributes)) {
            return $this;
        }

        $this->addAttributes($attributes);

        if ($this->serviceVersion === null && isset($attributes['git.tag'])) {
            $this->serviceVersion = $attributes['git.tag'];
        }

        if ($this->serviceVersion === null && isset($attributes['git.hash'])) {
            $this->serviceVersion = $attributes['git.hash'];
        }

        return $this;
    }

    public function attributesAsArray(): array
    {
        return array_merge($this->attributes, [
            'service.name' => $this->serviceName,
            'service.version' => $this->serviceVersion,
            'service.stage' => $this->serviceStage,
            'telemetry.sdk.language' => 'php',
            'telemetry.sdk.name' => $this->telemetrySdkName,
            'telemetry.sdk.version' => $this->telemetrySdkVersion,
        ]);
    }

    public function toArray(): array
    {
        return [
            'attributes' => $this->attributesAsArray(),
            'droppedAttributesCount' => $this->droppedAttributesCount,
        ];
    }
}
