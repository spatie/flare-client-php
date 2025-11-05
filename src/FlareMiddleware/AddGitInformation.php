<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\AttributesProviders\GitAttributesProvider;
use Spatie\FlareClient\ReportFactory;

class AddGitInformation implements FlareMiddleware
{
    const DEFAULT_USE_PROCESS = false;

    protected bool $useProcess;

    public static function register(ContainerInterface $container, array $config): Closure
    {
        return fn () => new static(
            $container->get(GitAttributesProvider::class),
            $config,
        );
    }

    public function __construct(
        protected GitAttributesProvider $gitAttributesProvider,
        protected array $config,
    ) {
        $this->useProcess = $this->config['use_process'] ?? self::DEFAULT_USE_PROCESS;
    }

    public function handle(ReportFactory $report, Closure $next): ReportFactory
    {
        $report->addAttributes($this->gitAttributesProvider->toArray($this->useProcess));

        return $next($report);
    }
}
