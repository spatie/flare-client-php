<?php

namespace Spatie\FlareClient\FlareMiddleware;

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Performance\Tracer;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Spatie\FlareClient\Report;
use Spatie\FlareClient\Support\Container;

class AddGlows implements FlareMiddleware, ContainerAwareFlareMiddleware
{
    protected GlowRecorder $recorder;

    public function __construct(
        protected ?int $maxGlows = 30,
        protected bool $traceGlows = false
    )
    {
    }

    public function handle(Report $report, Closure $next)
    {
        $report->setGlows($this->recorder->getGlows());

        return $next($report);
    }

    public function register(ContainerInterface|Container $container): void
    {
        $container->singleton(GlowRecorder::class, fn() => new GlowRecorder(
            $container->get(Tracer::class),
            $this->maxGlows,
            $this->traceGlows
        ));
    }

    public function boot(ContainerInterface|Container $container): void
    {
        $this->recorder = $container->get(GlowRecorder::class);
    }
}
