<?php

namespace Spatie\FlareClient\Recorders\ContextRecorder;

use Spatie\FlareClient\Contracts\Recorders\Recorder;
use Spatie\FlareClient\Enums\RecorderType;

/**
 * @phpstan-type ContextArray array<int|string, mixed>
 */
class ContextRecorder implements Recorder
{
    /** @var array<string, ContextArray> */
    protected array $context = [];

    public static function type(): string|RecorderType
    {
        return RecorderType::Context;
    }

    public function boot(): void
    {

    }

    public function reset(): void
    {
        $this->context = [];
    }


    public function record(
        string $group,
        string|array $key,
        mixed $value = null
    ): self {
        if (! array_key_exists($group, $this->context)) {
            $this->context[$group] = [];
        }

        if (is_array($key)) {
            $this->context[$group] = $this->arrayMergeRecursiveDistinct(
                $this->context[$group],
                $key
            );

            return $this;
        }

        $this->context[$group][$key] = $value;

        return $this;
    }

    /** @return array<string, ContextArray> */
    public function toArray(): array
    {
        return $this->context;
    }

    /**
     * @param ContextArray $array1
     * @param ContextArray $array2
     *
     * @return ContextArray
     */
    protected function arrayMergeRecursiveDistinct(array &$array1, array &$array2): array
    {
        $merged = $array1;

        foreach ($array2 as $key => &$value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->arrayMergeRecursiveDistinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}
