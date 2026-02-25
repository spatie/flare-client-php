<?php

namespace Spatie\FlareClient\Senders;

use Closure;
use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\Senders\Exceptions\ConnectionError;
use Spatie\FlareClient\Senders\Exceptions\DaemonTimeoutException;
use Spatie\FlareClient\Senders\Support\Response;
use Spatie\FlareClient\Support\DaemonConnection;

class DaemonSender implements Sender
{
    protected string $daemonUrl;

    protected int $testTimeout;

    protected ?Closure $onTestAckCallback = null;

    public function __construct(
        protected array $config = []
    ) {
        $this->daemonUrl = $this->config['daemon_url'] ?? '127.0.0.1:8787';
        $this->testTimeout = $this->config['test_timeout'] ?? 30;
    }

    /**
     * @param Closure(Closure): void $callback
     */
    public function onTestAck(Closure $callback): void
    {
        $this->onTestAckCallback = $callback;
    }

    public function post(string $endpoint, string $apiToken, array $payload, FlareEntityType $type, bool $test, Closure $callback): void
    {
        $connection = DaemonConnection::create($this->daemonUrl);

        $daemonType = $test ? "{$type->value}_test" : $type->value;

        $jsonPayload = json_encode($payload);

        if ($jsonPayload === false) {
            throw new ConnectionError('Invalid JSON payload provided');
        }

        $message = "v1:{$daemonType}:{$jsonPayload}";
        $frame = strlen($message) . ":{$message}";

        $connection->write($frame);

        $ack = $connection->read();

        if ($ack !== '2:OK') {
            throw new ConnectionError("Unexpected daemon response: {$ack}");
        }

        if (! $test) {
            return;
        }

        if ($this->onTestAckCallback !== null) {
            ($this->onTestAckCallback)();
            $this->onTestAckCallback = null;
        }

        try {
            $response = $connection->readWithTimeout($this->testTimeout);
        } catch (ConnectionError $e) {
            if (str_contains($e->getMessage(), 'Timed out')) {
                throw new DaemonTimeoutException($this->testTimeout);
            }

            throw $e;
        }

        $parsed = $this->parseTestResponse($response, $daemonType);

        $callback(new Response($parsed['statusCode'], $parsed['body']));
    }

    /**
     * @return array{statusCode: int, body: mixed}
     */
    private function parseTestResponse(string $response, string $expectedType): array
    {
        $colonPos = strpos($response, ':');

        if ($colonPos === false) {
            throw new ConnectionError("Invalid daemon test response format: {$response}");
        }

        $rest = substr($response, $colonPos + 1);

        $colonPos = strpos($rest, ':');

        if ($colonPos === false) {
            throw new ConnectionError("Invalid daemon test response format: missing type");
        }

        $responseType = substr($rest, 0, $colonPos);
        $rest = substr($rest, $colonPos + 1);

        if ($responseType !== $expectedType) {
            throw new ConnectionError("Unexpected daemon response type: expected {$expectedType}, got {$responseType}");
        }

        $colonPos = strpos($rest, ':');

        if ($colonPos === false) {
            throw new ConnectionError("Invalid daemon test response format: missing status code");
        }

        $statusCode = (int) substr($rest, 0, $colonPos);
        $responseBody = substr($rest, $colonPos + 1);

        /** @var mixed $body */
        $body = json_decode($responseBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $body = $responseBody;
        }

        return ['statusCode' => $statusCode, 'body' => $body];
    }
}
