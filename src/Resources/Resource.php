<?php

namespace Spatie\FlareClient\Resources;

use Composer\InstalledVersions;
use Spatie\FlareClient\AttributesProviders\GitAttributesProvider;
use Spatie\FlareClient\Concerns\HasAttributes;
use Spatie\FlareClient\Support\Telemetry;

/** @see https://github.com/opentelemetry-php/sdk/blob/main/Resource/Detectors/ */
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

    public function composer(): self
    {
        $this->attributes['service.name'] = InstalledVersions::getRootPackage()['name'];
        $this->attributes['service.version'] = InstalledVersions::getRootPackage()['pretty_version'];
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
        $this->attributes['process.pid'] =  getmypid();
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
        $this->attributes['process.runtime.name'] = php_sapi_name();
        $this->attributes['process.runtime.version'] = PHP_VERSION;
    }

    public function git(): self
    {
        $attributes = (new GitAttributesProvider())->toArray();

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
            'attributes' => $this->attributesAsArray(),
            'droppedAttributesCount' => $this->droppedAttributesCount,
        ];
    }
}
