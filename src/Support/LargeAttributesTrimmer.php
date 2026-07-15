<?php

namespace Spatie\FlareClient\Support;

use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;

class LargeAttributesTrimmer
{
    protected const LEAF_BYTES = 4;

    public function trim(Span|SpanEvent $entry, int $budgetInKb): void
    {
        if ($budgetInKb <= 0) {
            return;
        }

        [$entry->attributes, $dropped] = $this->limit($entry->attributes, $budgetInKb * 1024);

        $entry->droppedAttributesCount += $dropped;
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array{array<string, mixed>, int}
     */
    protected function limit(array $attributes, int $budget): array
    {
        $dropped = 0;

        foreach ($attributes as $key => $value) {
            if (! is_string($value) && ! is_array($value)) {
                continue;
            }

            $remaining = $budget;

            if ($this->walk($value, $remaining)) {
                unset($attributes[$key]);

                $dropped++;
            }
        }

        return [$attributes, $dropped];
    }

    protected function walk(mixed $value, int &$remaining): bool
    {
        if (is_string($value)) {
            $remaining -= strlen($value);

            return $remaining < 0;
        }

        if (! is_array($value)) {
            $remaining -= self::LEAF_BYTES;

            return $remaining < 0;
        }

        foreach ($value as $key => $item) {
            $remaining -= is_string($key) ? strlen($key) : self::LEAF_BYTES;

            if ($remaining < 0) {
                return true;
            }

            if ($this->walk($item, $remaining)) {
                return true;
            }
        }

        return false;
    }
}
