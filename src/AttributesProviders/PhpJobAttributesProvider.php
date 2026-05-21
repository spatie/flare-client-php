<?php

namespace Spatie\FlareClient\AttributesProviders;

use Spatie\FlareClient\Contracts\JobAttributesProvider;

class PhpJobAttributesProvider implements JobAttributesProvider
{
    public function __construct(
        protected string $jobName,
        protected ?string $jobClass = null,
    ) {
    }

    public function toArray(): array
    {
        return [];
    }

    public function jobName(): string
    {
        return $this->jobName;
    }

    public function jobClass(): ?string
    {
        return $this->jobClass;
    }

    public function entryPointHandlerIdentifier(): ?string
    {
        return $this->jobName;
    }

    public function entryPointHandlerName(): ?string
    {
        return $this->jobClass;
    }

    public function entryPointHandlerType(): ?string
    {
        return 'php_job';
    }
}
