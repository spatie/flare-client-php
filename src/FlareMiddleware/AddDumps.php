<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Contracts\Recorder;
use Spatie\FlareClient\Performance\Tracer;
use Spatie\FlareClient\Recorders\DumpRecorder\DumpRecorder;
use Spatie\FlareClient\Report;

/**
 * @implements RecordingMiddleware<DumpRecorder>
 */
class AddDumps implements FlareMiddleware, RecordingMiddleware
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

    public function setupRecording(Closure $setup): void
    {
        $setup(
            DumpRecorder::class,
            fn (ContainerInterface $container) => new DumpRecorder(
                $container->get(Tracer::class),
                $this->maxDumps,
                $this->traceDumps,
                $this->traceDumpOrigins,
            ),
            fn (DumpRecorder $recorder) => $this->dumpRecorder = $recorder,
        );
    }

    public function getRecorder(): Recorder
    {
        return $this->dumpRecorder;
    }
}
