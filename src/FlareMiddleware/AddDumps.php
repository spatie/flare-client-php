<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Performance\Tracer;
use Spatie\FlareClient\Recorders\DumpRecorder\DumpRecorder;
use Spatie\FlareClient\Report;
use Spatie\FlareClient\Support\Container;

class AddDumps implements FlareMiddleware, ContainerAwareFlareMiddleware
{
    protected DumpRecorder $dumpRecorder;

    public function __construct(
        protected ?int $maxDumps = 300,
        protected bool $traceDumps = false,
        protected bool $traceDumpOrigins = false,
    ) {
    }

    public function handle(Report $report, Closure $next)
    {
        $report->group('dumps', $this->dumpRecorder->getDumps());

        return $next($report);
    }

    public function register(Container|ContainerInterface $container): void
    {
        $container->singleton(DumpRecorder::class, fn () => new DumpRecorder(
            $container->get(Tracer::class),
            $this->maxDumps,
            $this->traceDumps,
            $this->traceDumpOrigins,
        ));
    }

    public function boot(Container|ContainerInterface $container): void
    {
        $this->dumpRecorder = $container->get(DumpRecorder::class);
    }
}
