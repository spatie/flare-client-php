<?php

namespace Spatie\FlareClient\Tests\Shared;

use Closure;
use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Senders\Support\Response;

class FakeSender implements Sender
{
    /** @var array<int, array{endpoint: string, apiToken: string, payload: array, type: FlareEntityType, test: bool}> */
    public static array $sent = [];

    public static ?int $responseCode = 200;

    public static mixed $responseBody = null;

    public function post(
        string $endpoint,
        string $apiToken,
        array $payload,
        FlareEntityType $type,
        bool $test,
        Closure $callback,
    ): void {
        self::$sent[] = [
            'endpoint' => $endpoint,
            'apiToken' => $apiToken,
            'payload' => $payload,
            'type' => $type,
            'test' => $test,
        ];

        $response = new Response(
            code: self::$responseCode ?? 200,
            body: '',
        );

        $callback($response);
    }

    public static function reset(): void
    {
        self::$sent = [];
        self::$responseCode = 200;
    }

    public static function assertSent(
        ?int $reports = 0,
        ?int $traces = 0,
        ?int $logs = 0,
    ): void {
        if ($reports !== null) {
            $actualCount = count(array_filter(self::$sent, fn ($item) => $item['type'] === FlareEntityType::Errors));

            expect($actualCount)->toBe($reports, "Expected {$reports} report requests, but {$actualCount} were sent.");
        }

        if ($traces !== null) {
            $actualCount = count(array_filter(self::$sent, fn ($item) => $item['type'] === FlareEntityType::Traces));

            expect($actualCount)->toBe($traces, "Expected {$traces} trace requests, but {$actualCount} were sent.");
        }

        if ($logs !== null) {
            $actualCount = count(array_filter(self::$sent, fn ($item) => $item['type'] === FlareEntityType::Logs));

            expect($actualCount)->toBe($logs, "Expected {$logs} log requests, but {$actualCount} were sent.");
        }
    }


    public static function assertNothingSent(): void
    {
        self::assertSent(0, 0, 0);
    }

    public static function setResponseCode(int $code): void
    {
        self::$responseCode = $code;
    }
}
