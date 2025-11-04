<?php

namespace Spatie\FlareClient\Resources;

use Spatie\FlareClient\AttributesProviders\GitAttributesProvider;
use Spatie\FlareClient\Concerns\HasAttributes;
use Spatie\FlareClient\Contracts\WithAttributes;
use Spatie\FlareClient\Support\HostIpFetcher;
use Spatie\FlareClient\Support\Telemetry;

/** @see https://github.com/opentelemetry-php/sdk/blob/main/Resource/Detectors/ */
class Resource implements WithAttributes
{
    use HasAttributes;

    /**
     * @param array $attributes <string, mixed>
     */
    public function __construct(
        string $serviceName,
        ?string $serviceVersion = null,
        ?string $serviceStage = null,
        string $telemetrySdkName = Telemetry::NAME,
        string $telemetrySdkVersion = 'unknown',
        array $attributes = []
    ) {
        $this->attributes = [
            'service.name' => $serviceName,
            'service.version' => $serviceVersion,
            'service.stage' => $serviceStage,
            'telemetry.sdk.language' => 'php',
            'telemetry.sdk.name' => $telemetrySdkName,
            'telemetry.sdk.version' => $telemetrySdkVersion,
            ...$attributes,
        ];
    }

    public function serviceName(string $name): self
    {
        $this->addAttribute('service.name', $name);

        return $this;
    }

    public function serviceVersion(?string $version): self
    {
        $this->addAttribute('service.version', $version);

        return $this;
    }

    public function serviceStage(?string $stage): self
    {
        $this->addAttribute('service.stage', $stage);

        return $this;
    }

    public function telemetrySdkName(string $name): self
    {
        $this->addAttribute('telemetry.sdk.name', $name);

        return $this;
    }

    public function telemetrySdkVersion(string $version): self
    {
        $this->addAttribute('telemetry.sdk.version', $version);

        return $this;
    }

    public function composer(): self
    {
        return $this;
    }

    public function host(): self
    {
        if ($hostIp = HostIpFetcher::fetch()) {
            $this->attributes['host.ip'] = $hostIp;
        }

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
            $this->attributes['process.command_args'] = $_SERVER['argv'];
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

        if (isset($attributes['service.version']) === false && isset($attributes['git.tag'])) {
            $this->addAttribute('service.version', $attributes['git.tag']);
        }

        if (isset($attributes['service.version']) === false && isset($attributes['git.hash'])) {
            $this->addAttribute('service.version', $attributes['git.hash']);
        }

        return $this;
    }
}
