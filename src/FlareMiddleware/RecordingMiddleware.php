<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Contracts\Recorder;
use Spatie\FlareClient\Support\Container;

/**
 * @template T of Recorder
 */
interface RecordingMiddleware
{
    /**
     * @param Closure(class-string<T>, Closure(ContainerInterface): T, Closure(T): void):void $setup
     */
    public function setupRecording(
        Closure $setup,
    ): void;


    /**
     * @return T
     */
    public function getRecorder(): Recorder;
}
