<?php

namespace Spatie\FlareClient\Concerns\Recorders;

use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;

/**
 * @template T of Span|SpanEvent
 */
trait ErrorsRecorder
{
    /** @var array<T> */
    protected array $entries = [];

    protected bool $withErrors = false;

    protected ?int $maxItemsWithErrors = null;

    private function configureErrorsRecording(array $config): void
    {
        $this->withErrors = $config['with_errors'] ?? false;
        $this->maxItemsWithErrors = $config['max_items_with_errors'] ?? null;
    }

    private function shouldReport(): bool
    {
        return $this->withErrors;
    }

    private function addEntryToReport(mixed $entry): void
    {
        $this->entries[] = $entry;

        if ($this->maxItemsWithErrors && count($this->entries) > $this->maxItemsWithErrors) {
            array_shift($this->entries);
        }
    }

    final public function reset(): void
    {
        $this->entries = [];
    }
}
