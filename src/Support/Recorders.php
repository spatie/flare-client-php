<?php

namespace Spatie\FlareClient\Support;

use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Contracts\Recorders\Recorder;
use Spatie\FlareClient\Enums\CollectType;
use Spatie\FlareClient\Enums\RecorderType;

class Recorders
{
    /** @var array<value-of<RecorderType>|string, Recorder> */
    protected array $recorders = [];

    /**
     * @param array<class-string<Recorder>, array{type: CollectType, options: array<string, mixed>} $recorderDefinitions
     */
    public function __construct(
        protected array $recorderDefinitions,
    ) {

    }

    public function boot(ContainerInterface $container): void
    {
        foreach ($this->recorderDefinitions as $recorderClass => $definition) {
            /** @var class-string<Recorder> $recorderClass */
            $type = $recorderClass::type();

            $recorder = $container->get($recorderClass);

            $this->recorders[is_string($type) ? $type : $type->value] = $recorder;

            $recorder->boot();
        }
    }

    public function getRecorder(
        RecorderType|string $type,
    ): ?Recorder {
        return $this->recorders[is_string($type) ? $type : $type->value] ?? null;
    }

    public function all(): array
    {
        return $this->recorders;
    }

    public function reset(): void
    {
        foreach ($this->recorders as $recorder) {
            $recorder->reset();
        }
    }
}
