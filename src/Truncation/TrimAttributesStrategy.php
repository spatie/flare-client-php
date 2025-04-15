<?php

namespace Spatie\FlareClient\Truncation;

class TrimAttributesStrategy extends AbstractTruncationStrategy
{
    protected array $attributesToIgnore = [
        'flare.entry_point.type',
        'flare.entry_point.value',
        'flare.entry_point.class',

        'process.command_args',

        'laravel.job.class',
        'laravel.job.queue.name',
        'laravel.job.queue.connection_name',

        'http.request.method',
        'url.full',
        'laravel.route.name',
        'http.route',

        'user.id',
        'user.full_name',
        'user.email',
    ];

    /**
     * @return array<int, int>
     */
    public static function thresholds(): array
    {
        return [100, 50, 25, 10];
    }

    /**
     * @param array<int|string, mixed> $payload
     *
     * @return array<int|string, mixed>
     */
    public function execute(array $payload): array
    {
        foreach (static::thresholds() as $threshold) {
            if (! $this->reportTrimmer->needsToBeTrimmed($payload)) {
                break;
            }

            $payload['attributes'] = $this->iterateAttributes($payload['attributes'], $threshold);
        }

        return $payload;
    }

    /**
     * @param array<int|string, mixed> $attributes
     * @param int $threshold
     *
     * @return array<int|string, mixed>
     */
    protected function iterateAttributes(array $attributes, int $threshold): array
    {
        array_walk($attributes, [$this, 'trimAttributes'], $threshold);

        return $attributes;
    }

    protected function trimAttributes(mixed &$value, mixed $key, int $threshold): mixed
    {
        if (in_array($key, $this->attributesToIgnore)) {
            return $value;
        }

        if (is_array($value)) {
            if (count($value) > $threshold) {
                $value = array_slice($value, $threshold * -1, $threshold);
            }

            array_walk($value, [$this, 'trimAttributes'], $threshold);
        }

        return $value;
    }
}
