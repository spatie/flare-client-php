<?php

namespace Spatie\FlareClient\FlareMiddleware;

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Contracts\Recorder;
use Spatie\FlareClient\Performance\Tracer;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Spatie\FlareClient\Report;

/**
 * @implements RecordingMiddleware<GlowRecorder>
 */
class AddGlows implements FlareMiddleware, RecordingMiddleware
{
    protected GlowRecorder $recorder;

    public function __construct(
        protected ?int $maxGlows = 30,
        protected bool $traceGlows = false
    ) {
    }

    public function handle(Report $report, Closure $next)
    {
        $report->setGlows($this->recorder->getGlows());

        return $next($report);
    }

    public function setupRecording(Closure $setup): void
    {
        $setup(
            GlowRecorder::class,
            fn (ContainerInterface $container) => new GlowRecorder(
                $container->get(Tracer::class),
                $this->maxGlows,
                $this->traceGlows
            ),
            fn(GlowRecorder $recorder) => $this->recorder = $recorder
        );
    }

    public function getRecorder(): Recorder
    {
        return $this->recorder;
    }
}
