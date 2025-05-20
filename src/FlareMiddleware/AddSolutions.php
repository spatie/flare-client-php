<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\ErrorSolutions\Contracts\SolutionProviderRepository;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Support\Container;

class AddSolutions implements FlareMiddleware
{
    public static function register(ContainerInterface $container, array $config): Closure
    {
        return fn () => new self(
            $container->get(SolutionProviderRepository::class)
        );
    }

    public function __construct(
        protected SolutionProviderRepository $solutionProviderRepository
    ) {
    }

    public function handle(ReportFactory $report, Closure $next): ReportFactory
    {
        if ($report->throwable === null) {
            return $next($report);
        }

        $report->addSolutions(
            ...$this->solutionProviderRepository->getSolutionsForThrowable($report->throwable)
        );

        return $next($report);
    }

    public function boot(ContainerInterface|Container $container): void
    {
        $this->solutionProviderRepository = $container->get(SolutionProviderRepository::class);
    }
}
