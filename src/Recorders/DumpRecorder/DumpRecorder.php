<?php

namespace Spatie\FlareClient\Recorders\DumpRecorder;

use Psr\Container\ContainerInterface;
use ReflectionMethod;
use ReflectionProperty;
use Spatie\Backtrace\Frame;
use Spatie\FlareClient\Concerns\HasOriginAttributes;
use Spatie\FlareClient\Concerns\RecordsSpanEvents;
use Spatie\FlareClient\Contracts\Recorder;
use Spatie\FlareClient\Contracts\SpanEventsRecorder;
use Spatie\FlareClient\FlareMiddleware\AddDumps;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\Tracer;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\VarDumper;

class DumpRecorder implements SpanEventsRecorder
{
    use HasOriginAttributes;

    /** @use RecordsSpanEvents<DumpSpanEvent> */
    use RecordsSpanEvents;

    protected static MultiDumpHandler $multiDumpHandler;

    public static function initialize(ContainerInterface $container, array $config): static
    {
        return new self(
            tracer: $container->get(Tracer::class),
            traceDumps: $config['trace_dumps'],
            reportDumps: $config['report_dumps'],
            maxReportedDumps: $config['max_reported_dumps'],
            findDumpOrigins: $config['find_dump_origins'],
        );
    }

    public function __construct(
        protected Tracer $tracer,
        bool $traceDumps,
        bool $reportDumps,
        ?int $maxReportedDumps,
        protected bool $findDumpOrigins,
    ) {
        $this->traceSpanEvents = $traceDumps;
        $this->reportSpanEvents = $reportDumps;
        $this->maxReportedSpanEvents = $maxReportedDumps;
    }

    public function start(): void
    {
        $multiDumpHandler = new MultiDumpHandler();

        $this->ensureOriginalHandlerExists();

        $originalHandler = VarDumper::setHandler(fn ($dumpedVariable) => $multiDumpHandler->dump($dumpedVariable));

        $multiDumpHandler->addHandler($originalHandler);
        $multiDumpHandler->addHandler(fn ($var) => (new DumpHandler($this))->dump($var));

        static::$multiDumpHandler = $multiDumpHandler;
    }

    public function record(Data $data): void
    {
        $spanEvent = new DumpSpanEvent(
            htmlDump: (new HtmlDumper())->dump($data),
        );

        if ($this->findDumpOrigins) {
            $frame = $this->tracer->backTracer->after(function (Frame $frame) {
                return $frame->class === VarDumper::class && $frame->method === 'dump';
            });

            if ($frame) {
                $spanEvent->setOriginFrame($frame);
            }
        }

        $this->persistSpanEvent($spanEvent);
    }
    /*
     * Only the `VarDumper` knows how to create the orignal HTML or CLI VarDumper.
     * Using reflection and the private VarDumper::register() method we can force it
     * to create and register a new VarDumper::$handler before we'll overwrite it.
     * Of course, we only need to do this if there isn't a registered VarDumper::$handler.
     *
     * @throws \ReflectionException
     */

    protected function ensureOriginalHandlerExists(): void
    {
        $reflectionProperty = new ReflectionProperty(VarDumper::class, 'handler');
        $reflectionProperty->setAccessible(true);
        $handler = $reflectionProperty->getValue();

        if (! $handler) {
            // No handler registered yet, so we'll force VarDumper to create one.
            $reflectionMethod = new ReflectionMethod(VarDumper::class, 'register');
            $reflectionMethod->setAccessible(true);
            $reflectionMethod->invoke(null);
        }
    }
}
