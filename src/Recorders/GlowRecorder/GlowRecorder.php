<?php

namespace Spatie\FlareClient\Recorders\GlowRecorder;

use Spatie\FlareClient\Concerns\RecordsSpanEvents;
use Spatie\FlareClient\Performance\Tracer;

class GlowRecorder
{
    /**  @use RecordsSpanEvents<GlowSpanEvent> */
    use RecordsSpanEvents;

    public function __construct(
        protected Tracer $tracer,
        ?int $maxGlows,
        bool $traceGlows,
    ) {
        $this->maxEntries = $maxGlows;
        $this->traceSpanEvents = $traceGlows;
    }

    /** @var array<int, GlowSpanEvent> */
    protected array $glows = [];

    public function record(GlowSpanEvent $glow): void
    {
        $this->persistSpanEvent($glow);
    }

    /** @return array<int, array<string, mixed>> */
    public function getGlows(): array
    {
        $glows = [];

        foreach ($this->spanEvents as $query) {
            $glows[] = $query->toOriginalFlareFormat();
        }

        return $glows;
    }
}
