<?php

namespace Spatie\FlareClient\Glows;

class GlowRecorder
{
    const GLOW_LIMIT = 30;

    protected array $glows = [];

    public function record(Glow $glow): void
    {
        $this->glows[] = $glow;

        $this->glows = array_slice($this->glows, static::GLOW_LIMIT * -1, static::GLOW_LIMIT);
    }

    public function glows(): array
    {
        return $this->glows;
    }

    public function reset(): void
    {
        $this->glows = [];
    }
}
