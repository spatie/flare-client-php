<?php

namespace Spatie\FlareClient\Recorders\DumpRecorder;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Spatie\Backtrace\Frame;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Recorders\Recorder;
use Spatie\FlareClient\Recorders\SpanEventsRecorder;
use Spatie\FlareClient\Spans\SpanEvent;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\VarDumper;

class DumpRecorder extends SpanEventsRecorder
{
    public const DEFAULT_MAX_ITEMS_WITH_ERRORS = 25;

    protected static MultiDumpHandler $multiDumpHandler;

    public static function type(): string|RecorderType
    {
        return RecorderType::Dump;
    }

    protected function configure(array $config): void
    {
        $this->findOriginThreshold = null;
    }

    public function boot(): void
    {
        $multiDumpHandler = new MultiDumpHandler();

        $this->ensureOriginalHandlerExists();

        $originalHandler = VarDumper::setHandler(fn ($dumpedVariable) => $multiDumpHandler->dump($dumpedVariable));

        $multiDumpHandler->addHandler($originalHandler);
        $multiDumpHandler->addHandler(fn ($var) => (new DumpHandler($this))->dump($var));

        static::$multiDumpHandler = $multiDumpHandler;
    }

    public function record(Data $data): ?SpanEvent
    {
        return $this->spanEvent(
            name: 'Dump entry',
            attributes: fn () => [
                'flare.span_event_type' => SpanEventType::Dump,
                'dump.html' => (new HtmlDumper())->dump($data),
            ],
            spanEventCallback: fn (SpanEvent $spanEvent) => $this->backtraceEntry(
                $spanEvent,
                frameAfter: fn (Frame $frame) => $frame->class === VarDumper::class && $frame->method === 'dump'
            )
        );
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
