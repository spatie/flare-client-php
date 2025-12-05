<?php

namespace Spatie\FlareClient\Resources;

use Composer\InstalledVersions;
use Spatie\FlareClient\AttributesProviders\GitAttributesProvider;
use Spatie\FlareClient\Concerns\HasAttributes;
use Spatie\FlareClient\Contracts\WithAttributes;
use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\Enums\ResourceIncludeType;
use Spatie\FlareClient\Support\HostIpFetcher;
use Spatie\FlareClient\Support\Telemetry;

class Resource implements WithAttributes
{
    use HasAttributes;

    public const DEFAULT_HOST_ENTITY_TYPES = [
        FlareEntityType::Errors,
        FlareEntityType::Traces,
        FlareEntityType::Logs,
    ];

    public const DEFAULT_OS_ENTITY_TYPES = [
        FlareEntityType::Errors,
        FlareEntityType::Traces,
    ];

    public const DEFAULT_PHP_ENTITY_TYPES = [
        FlareEntityType::Errors,
        FlareEntityType::Traces,
    ];

    public const DEFAULT_COMPOSER_PACKAGES_ENTITY_TYPES = [];

    public const DEFAULT_GIT_ENTITY_TYPES = [
        FlareEntityType::Errors,
        FlareEntityType::Traces,
    ];

    public const DEFAULT_GIT_USE_PROCESS = false;

    /** @var array<value-of<ResourceIncludeType>, array<string, mixed>> */
    protected array $includes = [];

    /** @var array<value-of<ResourceIncludeType>, array<string, mixed>> */
    protected array $cachedIncludes = [];

    /**
     * @param array $attributes <string, mixed>
     */
    public function __construct(
        public string $serviceName,
        public ?string $serviceVersion = null,
        public ?string $serviceStage = null,
        public string $telemetrySdkName = Telemetry::NAME,
        public string $telemetrySdkVersion = 'unknown',
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

    public function telemetrySdkName(string $name): self
    {
        $this->telemetrySdkName = $name;

        return $this;
    }

    public function telemetrySdkVersion(string $version): self
    {
        $this->telemetrySdkVersion = $version;

        return $this;
    }

    public function composer(): self
    {
        return $this; // Kept here for backward compatibility.
    }

    public function composerPackages(
        array $entityTypes = self::DEFAULT_COMPOSER_PACKAGES_ENTITY_TYPES
    ): self {
        return $this->addInclude(ResourceIncludeType::ComposerPackages, $entityTypes);
    }

    public function host(
        array $entityTypes = self::DEFAULT_HOST_ENTITY_TYPES
    ): self {
        return $this->addInclude(ResourceIncludeType::Host, $entityTypes);
    }

    public function operatingSystem(
        array $entityTypes = self::DEFAULT_OS_ENTITY_TYPES
    ): self {
        return $this->addInclude(ResourceIncludeType::OperatingSystem, $entityTypes);
    }

    public function process(
        array $entityTypes = self::DEFAULT_PHP_ENTITY_TYPES
    ): self {
        return $this->addInclude(ResourceIncludeType::Process, $entityTypes);
    }

    public function processRuntime(
        array $entityTypes = self::DEFAULT_PHP_ENTITY_TYPES
    ): self {
        return $this->addInclude(ResourceIncludeType::ProcessRuntime, $entityTypes);
    }

    public function git(
        GitAttributesProvider $attributesProvider = new GitAttributesProvider(),
        bool $useProcess = self::DEFAULT_GIT_USE_PROCESS,
        array $entityTypes = self::DEFAULT_GIT_ENTITY_TYPES
    ): self {
        return $this->addInclude(
            ResourceIncludeType::Git,
            $entityTypes,
            compact('useProcess', 'attributesProvider')
        );
    }

    public function export(
        FlareEntityType $type
    ): array {
        $includes = [
            ResourceIncludeType::Base->value => [],
        ];

        foreach ($this->includes as $include => $options) {
            if (in_array($type, $options['entity_types'])) {
                $includes[$include] = $options['arguments'] ?? [];
            }
        }

        $includes[ResourceIncludeType::CustomAttributes->value] = [];

        $attributeSets = [];

        foreach ($includes as $includeType => $arguments) {
            if (array_key_exists($includeType, $this->cachedIncludes)) {
                $attributeSets[$includeType] = $this->cachedIncludes[$includeType];

                continue;
            }

            $method = "resolve{$includeType}";

            if (! method_exists($this, $method)) {
                continue;
            }

            $attributeSets[] = $this->{$method}(...$arguments);
        }

        return array_merge(...$attributeSets);
    }

    /**
     * @param ResourceIncludeType|value-of<ResourceIncludeType> $includeType
     * @param array<int, FlareEntityType> $entityTypes
     * @param array<string, mixed> $arguments
     *
     * @return $this
     */
    protected function addInclude(
        ResourceIncludeType|string $includeType,
        array $entityTypes,
        array $arguments = []
    ): self {
        $this->includes[is_string($includeType) ? $includeType : $includeType->value] = [
            'entity_types' => $entityTypes,
            'arguments' => $arguments,
        ];

        return $this;
    }

    protected function resolveBase(): array
    {
        return [
            'service.name' => $this->serviceName,
            'service.version' => $this->serviceVersion,
            'service.stage' => $this->serviceStage,
            'telemetry.sdk.language' => 'php',
            'telemetry.sdk.name' => $this->telemetrySdkName,
            'telemetry.sdk.version' => $this->telemetrySdkVersion,
        ];
    }

    protected function resolveCustomAttributes(): array
    {
        return $this->attributes;
    }

    protected function resolveComposerPackages(): array
    {
        $packages = [];

        foreach (InstalledVersions::getAllRawData() as $data) {
            foreach ($data['versions'] as $packageName => $packageData) {
                $packages[$packageName] = $packageData['version'] ?? 'unknown';
            }
        }

        return [
            'composer.packages' => $packages,
        ];
    }

    protected function resolveHost(): array
    {
        $attributes = [];

        if ($hostIp = HostIpFetcher::fetch()) {
            $attributes['host.ip'] = $hostIp;
        }

        $attributes['host.name'] = php_uname('n');
        $attributes['host.arch'] = php_uname('m');

        return $attributes;
    }

    protected function resolveOperatingSystem(): array
    {
        return [
            'os.type' => strtolower(PHP_OS_FAMILY),
            'os.description' => php_uname('r'),
            'os.name' => PHP_OS,
            'os.version' => php_uname('v'),
        ];
    }

    protected function resolveProcess(): array
    {
        $attributes = [
            'process.pid' => getmypid(),
            'process.executable.path' => PHP_BINARY,
        ];

        if ($_SERVER['argv'] ?? null) {
            $attributes['process.command'] = $_SERVER['argv'][0];
            $attributes['process.command_args'] = $_SERVER['argv'];
        }

        if (extension_loaded('posix') && ($user = \posix_getpwuid(\posix_geteuid())) !== false) {
            $attributes['process.owner'] = $user['name'];
        }

        return $attributes;
    }

    protected function resolveProcessRuntime(): array
    {
        return [
            'process.runtime.name' => "PHP (".php_sapi_name().")",
            'process.runtime.version' => PHP_VERSION,
        ];
    }

    public function resolveGit(
        GitAttributesProvider $attributesProvider = new GitAttributesProvider(),
        bool $useProcess = self::DEFAULT_GIT_USE_PROCESS,
    ): array {
        $attributes = $attributesProvider->toArray($useProcess);

        if (empty($attributes)) {
            return $attributes;
        }

        if (isset($this->serviceVersion) === false && isset($attributes['git.tag'])) {
            $this->serviceVersion = $attributes['git.tag'];
        }

        if (isset($this->serviceVersion) === false && isset($attributes['git.hash'])) {
            $this->serviceVersion = $attributes['git.hash'];
        }

        return $attributes;
    }
}
