<?php

use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\Senders\CurlSender;
use Spatie\FlareClient\Senders\DaemonSender;
use Spatie\FlareClient\Senders\Exceptions\ConnectionError;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Senders\Support\Response;
use Spatie\FlareClient\Tests\Shared\FakeDaemonSender;
use Spatie\FlareClient\Tests\Shared\FakeSender;

function fakeDaemonSender(array $config = []): FakeDaemonSender
{
    return new FakeDaemonSender(array_merge([
        'daemon_url' => 'http://127.0.0.1:8787',
    ], $config));
}

it('falls back to direct delivery when the daemon is unreachable', function () {
    $sender = fakeDaemonSender(['fallback_sender_config' => []])
        ->onSend(fn () => throw new ConnectionError('Connection refused'))
        ->withFallbackSender(new FakeSender());

    $callbackInvoked = false;

    $sender->post(
        endpoint: 'https://ingress.flareapp.io/v1/errors',
        apiToken: 'fake-api-key',
        payload: ['message' => 'hello'],
        type: FlareEntityType::Errors,
        test: false,
        callback: function () use (&$callbackInvoked) {
            $callbackInvoked = true;
        },
    );

    expect($callbackInvoked)->toBeTrue();
    FakeSender::assertSent(reports: 1);
    expect($sender->warnings)->toHaveCount(1)
        ->and($sender->warnings[0]['message'])->toBe('Flare daemon delivery failed, falling back to direct delivery');
});

it('falls back to direct delivery when the daemon returns an unexpected response', function () {
    $sender = fakeDaemonSender(['fallback_sender_config' => []])
        ->onSend(fn () => new Response(500, ['message' => 'Daemon failed']))
        ->withFallbackSender(new FakeSender());

    $callbackInvoked = false;

    $sender->post(
        endpoint: 'https://ingress.flareapp.io/v1/errors',
        apiToken: 'fake-api-key',
        payload: ['message' => 'hello'],
        type: FlareEntityType::Errors,
        test: false,
        callback: function () use (&$callbackInvoked) {
            $callbackInvoked = true;
        },
    );

    expect($callbackInvoked)->toBeTrue();
    FakeSender::assertSent(reports: 1);
    expect($sender->warnings)->toHaveCount(1)
        ->and($sender->warnings[0]['message'])->toBe('Flare daemon delivery failed, falling back to direct delivery');
});

it('falls back when daemon returns a non-202 success code', function () {
    $sender = fakeDaemonSender(['fallback_sender_config' => []])
        ->onSend(fn () => new Response(200, ['status' => 'ok']))
        ->withFallbackSender(new FakeSender());

    $callbackInvoked = false;

    $sender->post(
        endpoint: 'https://ingress.flareapp.io/v1/errors',
        apiToken: 'fake-api-key',
        payload: ['message' => 'hello'],
        type: FlareEntityType::Errors,
        test: false,
        callback: function () use (&$callbackInvoked) {
            $callbackInvoked = true;
        },
    );

    expect($callbackInvoked)->toBeTrue();
    FakeSender::assertSent(reports: 1);
    expect($sender->warnings)->toHaveCount(1);
});

it('does not fall back for test payloads', function () {
    $sender = fakeDaemonSender()
        ->onSend(fn () => throw new ConnectionError('Connection refused'))
        ->withFallbackSender(new FakeSender());

    expect(fn () => $sender->post(
        endpoint: 'https://ingress.flareapp.io/v1/errors',
        apiToken: 'fake-api-key',
        payload: ['message' => 'hello'],
        type: FlareEntityType::Errors,
        test: true,
        callback: fn () => null,
    ))->toThrow(ConnectionError::class);

    FakeSender::assertNothingSent();
});

it('does not fall back when test payloads receive an unexpected daemon response', function () {
    $sender = fakeDaemonSender()
        ->onSend(fn () => new Response(500, ['message' => 'Daemon failed']))
        ->withFallbackSender(new FakeSender());

    expect(fn () => $sender->post(
        endpoint: 'https://ingress.flareapp.io/v1/errors',
        apiToken: 'fake-api-key',
        payload: ['message' => 'hello'],
        type: FlareEntityType::Errors,
        test: true,
        callback: function (Response $response) {
            if ($response->code !== 202) {
                throw new RuntimeException('Unexpected daemon response');
            }
        },
    ))->toThrow(RuntimeException::class);

    FakeSender::assertNothingSent();
});

it('passes through successful daemon responses for test payloads', function () {
    $capturedTimeout = 0;

    $sender = fakeDaemonSender()
        ->onSend(function (FlareEntityType $type, string $apiToken, array $payload, bool $test, int $timeout) use (&$capturedTimeout) {
            $capturedTimeout = $timeout;

            return new Response(202, ['status' => 'accepted']);
        });

    $response = null;

    $sender->post(
        endpoint: 'https://ingress.flareapp.io/v1/errors',
        apiToken: 'fake-api-key',
        payload: ['message' => 'hello'],
        type: FlareEntityType::Errors,
        test: true,
        callback: function (Response $callbackResponse) use (&$response) {
            $response = $callbackResponse;
        },
    );

    expect($response?->code)->toBe(202)
        ->and($response?->body)->toBe(['status' => 'accepted'])
        ->and($capturedTimeout)->toBe(10);
});

it('passes through daemon 403 responses for test payloads', function () {
    $sender = fakeDaemonSender()->onSend(fn () => new Response(403, 'Invalid API key'));

    $response = null;

    $sender->post(
        endpoint: 'https://ingress.flareapp.io/v1/errors',
        apiToken: 'fake-api-key',
        payload: ['message' => 'hello'],
        type: FlareEntityType::Errors,
        test: true,
        callback: function (Response $callbackResponse) use (&$response) {
            $response = $callbackResponse;
        },
    );

    expect($response?->code)->toBe(403)
        ->and($response?->body)->toBe('Invalid API key');
});

it('passes through daemon 422 responses for test payloads', function () {
    $sender = fakeDaemonSender()->onSend(fn () => new Response(422, [
        'message' => 'The given data was invalid.',
        'errors' => ['payload' => ['Invalid']],
    ]));

    $response = null;

    $sender->post(
        endpoint: 'https://ingress.flareapp.io/v1/errors',
        apiToken: 'fake-api-key',
        payload: ['message' => 'hello'],
        type: FlareEntityType::Errors,
        test: true,
        callback: function (Response $callbackResponse) use (&$response) {
            $response = $callbackResponse;
        },
    );

    expect($response?->code)->toBe(422)
        ->and($response?->body)->toBe([
            'message' => 'The given data was invalid.',
            'errors' => ['payload' => ['Invalid']],
        ]);
});

it('only logs once when daemon delivery fails before direct fallback also fails', function () {
    $failingSender = new class implements Sender {
        public function post(string $endpoint, string $apiToken, array $payload, FlareEntityType $type, bool $test, Closure $callback): void
        {
            throw new ConnectionError('Fallback failed');
        }
    };

    $sender = fakeDaemonSender(['fallback_sender_config' => []])
        ->onSend(fn () => throw new ConnectionError('Connection refused'))
        ->withFallbackSender($failingSender);

    expect(fn () => $sender->post(
        endpoint: 'https://ingress.flareapp.io/v1/errors',
        apiToken: 'fake-api-key',
        payload: ['message' => 'hello'],
        type: FlareEntityType::Errors,
        test: false,
        callback: fn () => null,
    ))->toThrow(ConnectionError::class, 'Could not perform request because: Fallback failed');

    expect($sender->warnings)->toHaveCount(1)
        ->and($sender->warnings[0]['message'])->toBe('Flare daemon delivery failed, falling back to direct delivery');
});

it('uses the normal timeout for non-test payloads', function () {
    $capturedTimeout = 0;

    $sender = fakeDaemonSender()
        ->onSend(function (FlareEntityType $type, string $apiToken, array $payload, bool $test, int $timeout) use (&$capturedTimeout) {
            $capturedTimeout = $timeout;

            return new Response(202, ['status' => 'accepted']);
        });

    $sender->post(
        endpoint: 'https://ingress.flareapp.io/v1/errors',
        apiToken: 'fake-api-key',
        payload: ['message' => 'hello'],
        type: FlareEntityType::Errors,
        test: false,
        callback: fn () => null,
    );

    expect($capturedTimeout)->toBe(1);
});

it('uses curl sender as the default fallback transport', function () {
    $sender = new class(['daemon_url' => 'http://127.0.0.1:8787']) extends DaemonSender {
        public function fallbackSender(): Sender
        {
            return $this->createFallbackSender();
        }
    };

    expect($sender->fallbackSender())->toBeInstanceOf(CurlSender::class);
});

it('passes sender-owned fallback config to the internal curl sender', function () {
    $sender = new class(['daemon_url' => 'http://127.0.0.1:8787', 'fallback_sender_config' => ['timeout' => 3]]) extends DaemonSender {
        public function fallbackSender(): Sender
        {
            return $this->createFallbackSender();
        }
    };

    $fallbackSender = $sender->fallbackSender();
    $timeout = (new ReflectionProperty($fallbackSender, 'timeout'))->getValue($fallbackSender);

    expect($fallbackSender)->toBeInstanceOf(CurlSender::class)
        ->and($timeout)->toBe(3);
});
