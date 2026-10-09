<?php

namespace Spatie\FlareClient\Truncation;

class ReportTrimmer
{
    protected static int $maxPayloadSize = 524288;

    /** @var array<int, class-string<\Spatie\FlareClient\Truncation\TruncationStrategy>> */
    protected array $strategies = [
        TrimPreviousStrategy::class,
        TrimStackFrameArgumentsStrategy::class,
        TrimStringsStrategy::class,
        TrimAttributesStrategy::class,
    ];

    /**
     * @param array<int|string, mixed> $payload
     *
     * @return array<int|string, mixed>
     */
    public function trim(array $payload): array
    {
        foreach ($this->strategies as $strategy) {
            if (! $this->needsToBeTrimmed($payload)) {
                break;
            }

            $payload = (new $strategy($this))->execute($payload);
        }

        return $payload;
    }

    /**
     * @param array<int|string, mixed> $payload
     *
     * @return bool
     */
    public function needsToBeTrimmed(array $payload): bool
    {
        // Encoding never shortens strings, so skip encoding payloads that are too large anyway
        if ($this->sumStringLengths($payload) > self::getMaxPayloadSize()) {
            return true;
        }

        return strlen((string) json_encode($payload)) > self::getMaxPayloadSize();
    }

    /**
     * @param array<int|string, mixed> $values
     */
    protected function sumStringLengths(array $values): int
    {
        $length = 0;

        foreach ($values as $value) {
            if (is_string($value)) {
                $length += strlen($value);
            }

            if (is_array($value)) {
                $length += $this->sumStringLengths($value);
            }
        }

        return $length;
    }

    public static function getMaxPayloadSize(): int
    {
        return self::$maxPayloadSize;
    }

    public static function setMaxPayloadSize(int $maxPayloadSize): void
    {
        self::$maxPayloadSize = $maxPayloadSize;
    }
}
