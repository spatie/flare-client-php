<?php

namespace Spatie\FlareDaemon;

use DateTimeImmutable;
use DateTimeZone;

class QuotaState
{
    public const ENTITY_TYPES = ['errors', 'traces', 'logs'];

    /**
     * @var array<string, array<string, array{permanent: bool, retry_after: float|null, reason: string|null}>>
     */
    protected array $states = [];

    public function pause(string $apiKey, string $type, ?float $retryAfter, ?string $reason = null): void
    {
        $this->states[$apiKey][$type] = [
            'permanent' => false,
            'retry_after' => $retryAfter,
            'reason' => $reason,
        ];
    }

    public function pauseAll(string $apiKey, ?string $reason = null): void
    {
        foreach (self::ENTITY_TYPES as $type) {
            $this->states[$apiKey][$type] = [
                'permanent' => true,
                'retry_after' => null,
                'reason' => $reason,
            ];
        }
    }

    public function isPaused(string $apiKey, string $type, float $now): bool
    {
        $state = $this->states[$apiKey][$type] ?? null;

        if ($state === null) {
            return false;
        }

        if ($state['permanent']) {
            return true;
        }

        if ($state['retry_after'] !== null && $state['retry_after'] <= $now) {
            $this->resume($apiKey, $type);

            return false;
        }

        return $state['retry_after'] !== null;
    }

    public function isPermanent(string $apiKey, string $type): bool
    {
        return ($this->states[$apiKey][$type]['permanent'] ?? false) === true;
    }

    public function reason(string $apiKey, string $type): ?string
    {
        return $this->states[$apiKey][$type]['reason'] ?? null;
    }

    public function retryAfter(string $apiKey, string $type, float $now): ?string
    {
        if (! $this->isPaused($apiKey, $type, $now)) {
            return null;
        }

        $retryAfter = $this->states[$apiKey][$type]['retry_after'] ?? null;

        if ($retryAfter === null) {
            return null;
        }

        return (new DateTimeImmutable('@'.(string) (int) $retryAfter))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(DATE_ATOM);
    }

    /**
     * @return array<int, array{api_key: string, type: string}>
     */
    public function resumeExpired(float $now): array
    {
        $resumed = [];

        foreach ($this->states as $apiKey => $typeStates) {
            foreach ($typeStates as $type => $state) {
                if ($state['permanent']) {
                    continue;
                }

                if ($state['retry_after'] !== null && $state['retry_after'] <= $now) {
                    $this->resume($apiKey, $type);

                    $resumed[] = [
                        'api_key' => $apiKey,
                        'type' => $type,
                    ];
                }
            }
        }

        return $resumed;
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->states);
    }

    protected function resume(string $apiKey, string $type): void
    {
        unset($this->states[$apiKey][$type]);

        if (($this->states[$apiKey] ?? []) === []) {
            unset($this->states[$apiKey]);
        }
    }
}
