<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\ErrorSolutions\Contracts\SolutionProviderRepository;
use Spatie\FlareClient\Report;
use Spatie\FlareClient\Support\Container;

class AddSolutions implements FlareMiddleware, ContainerAwareFlareMiddleware
{
    protected SolutionProviderRepository $solutionProviderRepository;

    public function handle(Report $report, Closure $next)
    {
        if ($throwable = $report->getThrowable()) {
            $solutions = $this->solutionProviderRepository->getSolutionsForThrowable($throwable);

            foreach ($solutions as $solution) {
                $report->addSolution($solution);
            }
        }

        return $next($report);
    }

    public function register(ContainerInterface|Container $container): void
    {

    }

    public function boot(ContainerInterface|Container $container): void
    {
        $this->solutionProviderRepository = $container->get(SolutionProviderRepository::class);
    }
}
