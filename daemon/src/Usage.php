<?php

namespace Spatie\FlareDaemon;

class Usage
{
    public function __construct(
        public int $errorsUsed,
        public int $errorsLimit,
        public int $tracesUsed,
        public int $tracesLimit,
        public int $logsUsed,
        public int $logsLimit,
        public string $resetAt,
    ) {
    }

    public function isOverLimit(string $type): bool
    {
        return match ($type) {
            'errors' => $this->errorsUsed >= $this->errorsLimit,
            'traces' => $this->tracesUsed >= $this->tracesLimit,
            'logs' => $this->logsUsed >= $this->logsLimit,
            default => false,
        };
    }

    public function allOverLimit(): bool
    {
        return $this->isOverLimit('errors')
            && $this->isOverLimit('traces')
            && $this->isOverLimit('logs');
    }

    public function used(string $type): int
    {
        return match ($type) {
            'errors' => $this->errorsUsed,
            'traces' => $this->tracesUsed,
            'logs' => $this->logsUsed,
            default => 0,
        };
    }

    public function limit(string $type): int
    {
        return match ($type) {
            'errors' => $this->errorsLimit,
            'traces' => $this->tracesLimit,
            'logs' => $this->logsLimit,
            default => 0,
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            errorsUsed: self::intValue($data, 'errors_used'),
            errorsLimit: self::intValue($data, 'errors_limit'),
            tracesUsed: self::intValue($data, 'traces_used'),
            tracesLimit: self::intValue($data, 'traces_limit'),
            logsUsed: self::intValue($data, 'logs_used'),
            logsLimit: self::intValue($data, 'logs_limit'),
            resetAt: isset($data['reset_at']) && is_string($data['reset_at']) ? $data['reset_at'] : '',
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function intValue(array $data, string $key): int
    {
        if (! isset($data[$key])) {
            return 0;
        }

        $value = $data[$key];

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }
}
