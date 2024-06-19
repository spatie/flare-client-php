<?php

namespace Spatie\FlareClient\Recorders\DumpRecorder;

use ReflectionMethod;
use ReflectionProperty;
use Spatie\Backtrace\Frame;
use Spatie\FlareClient\Concerns\RecordsSpanEvents;
use Spatie\FlareClient\Performance\Concerns\HasOriginAttributes;
use Spatie\FlareClient\Performance\Tracer;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\VarDumper;

class DumpRecorder
{
    use HasOriginAttributes;

    /** @use RecordsSpanEvents<DumpSpanEvent> */
    use RecordsSpanEvents;

    protected static MultiDumpHandler $multiDumpHandler;

    public function __construct(
        protected Tracer $tracer,
        protected ?int $maxDumps = 300,
        protected bool $traceDumps = false,
        protected bool $traceDumpOrigins = false,
    ) {
        $this->initializeStorage();
    }

    public function start(): self
    {
        $multiDumpHandler = new MultiDumpHandler();

        $this->ensureOriginalHandlerExists();

        $originalHandler = VarDumper::setHandler(fn ($dumpedVariable) => $multiDumpHandler->dump($dumpedVariable));

        $multiDumpHandler->addHandler($originalHandler);
        $multiDumpHandler->addHandler(fn ($var) => (new DumpHandler($this))->dump($var));

        static::$multiDumpHandler = $multiDumpHandler;

        return $this;
    }

    public function record(Data $data): void
    {
        $spanEvent = new DumpSpanEvent(
            htmlDump: (new HtmlDumper())->dump($data),
        );

        if ($this->traceDumpOrigins) {
            $frame = $this->tracer->backTracer->after(function (Frame $frame) {
                return $frame->class === VarDumper::class && $frame->method === 'dump';
            });

            if ($frame) {
                $spanEvent->setOriginFrame($frame);
            }
        }

        $this->persistSpanEvent($spanEvent);
    }

    public function getDumps(): array
    {
        $dumps = [];

        foreach ($this->spanEvents as $spanEvent) {
            $dumps[] = $spanEvent->toOriginalFlareFormat();
        }

        return $dumps;
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
