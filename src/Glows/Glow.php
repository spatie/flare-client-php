<?php

namespace Spatie\FlareClient\Glows;

use Spatie\FlareClient\Concerns\UsesTime;
use Spatie\FlareClient\Enums\MessageLevels;

class Glow
{
    use UsesTime;

    protected string $name;

    protected array $metaData = [];

    protected string $messageLevel;

    protected float $microtime;

    public function __construct(
        string $name,
        string $messageLevel = MessageLevels::INFO,
        array $metaData = [],
        ?float $microtime = null
    ) {
        $this->name = $name;
        $this->messageLevel = $messageLevel;
        $this->metaData = $metaData;
        $this->microtime = $microtime ?? microtime(true);
    }

    public function toArray(): array
    {
        return [
            'time' => $this->getCurrentTime(),
            'name' => $this->name,
            'message_level' => $this->messageLevel,
            'meta_data' => $this->metaData,
            'microtime' => $this->microtime,
        ];
    }
}
