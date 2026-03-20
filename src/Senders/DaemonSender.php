<?php

namespace Spatie\FlareClient\Senders;

use Closure;
use CurlHandle;
use RuntimeException;
use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\Senders\Exceptions\ConnectionError;
use Spatie\FlareClient\Senders\Support\Response;
use Throwable;

class DaemonSender implements Sender
{
    protected string $daemonUrl;

    protected int $timeout;

    protected int $testTimeout;

    public function __construct(
        protected array $config = [],
    ) {
        $this->daemonUrl = rtrim($this->config['daemon_url'] ?? 'http://127.0.0.1:8787', '/');
        $this->timeout = $this->config['timeout'] ?? 1;
        $this->testTimeout = $this->config['test_timeout'] ?? 10;
    }

    public function post(string $endpoint, string $apiToken, array $payload, FlareEntityType $type, bool $test, Closure $callback): void
    {
        if ($test) {
            $callback($this->sendToDaemon(
                type: $type,
                apiToken: $apiToken,
                payload: $payload,
                test: true,
                timeout: $this->testTimeout,
            ));

            return;
        }

        try {
            $response = $this->sendToDaemon(
                type: $type,
                apiToken: $apiToken,
                payload: $payload,
                test: false,
                timeout: $this->timeout,
            );
        } catch (Throwable $throwable) {
            $this->logDaemonFailure($type, $throwable);
            $this->fallbackToDirectDelivery($endpoint, $apiToken, $payload, $type, $callback);

            return;
        }

        if ($response->code !== 202) {
            $this->logDaemonFailure($type, response: $response);
            $this->fallbackToDirectDelivery($endpoint, $apiToken, $payload, $type, $callback);

            return;
        }

        $callback($response);
    }

    protected function sendToDaemon(
        FlareEntityType $type,
        string $apiToken,
        array $payload,
        bool $test,
        int $timeout,
    ): Response {
        $encodedPayload = json_encode($payload);

        if ($encodedPayload === false) {
            throw new RuntimeException('Invalid JSON payload provided');
        }

        $url = "{$this->daemonUrl}/v1/{$type->value}";
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-API-Token: '.$apiToken,
        ];

        if ($test) {
            $headers[] = 'X-Flare-Test: 1';
        }

        $curlHandle = $this->getCurlHandle($url, $headers, $timeout);
        curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $encodedPayload);

        $rawResponse = curl_exec($curlHandle);

        if (! is_string($rawResponse)) {
            throw new ConnectionError(curl_error($curlHandle));
        }

        $statusCode = curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curlHandle);

        if ($error !== '') {
            throw new ConnectionError($error);
        }

        $decoded = json_decode($rawResponse, true);

        return new Response(
            $statusCode,
            json_last_error() === JSON_ERROR_NONE ? $decoded : $rawResponse,
        );
    }

    protected function getCurlHandle(string $url, array $headers, int $timeout): CurlHandle
    {
        $curlHandle = curl_init();

        curl_setopt($curlHandle, CURLOPT_URL, $url);
        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curlHandle, CURLOPT_USERAGENT, 'Flare Client Daemon Sender');
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, parse_url($url, PHP_URL_SCHEME) === 'https');
        curl_setopt($curlHandle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($curlHandle, CURLOPT_ENCODING, '');
        curl_setopt($curlHandle, CURLOPT_POST, true);

        return $curlHandle;
    }

    protected function createFallbackSender(): Sender
    {
        return new CurlSender($this->config['fallback_sender_config'] ?? []);
    }

    /**
     * @param Closure(Response): void $callback
     */
    protected function fallbackToDirectDelivery(
        string $endpoint,
        string $apiToken,
        array $payload,
        FlareEntityType $type,
        Closure $callback,
    ): void {
        $this->createFallbackSender()->post(
            endpoint: $endpoint,
            apiToken: $apiToken,
            payload: $payload,
            type: $type,
            test: false,
            callback: $callback,
        );
    }

    protected function logDaemonFailure(
        FlareEntityType $type,
        ?Throwable $exception = null,
        ?Response $response = null,
    ): void {
        $context = [
            'daemon_url' => $this->daemonUrl,
            'type' => $type->value,
        ];

        if ($exception !== null) {
            $context['exception'] = $exception;
        }

        if ($response !== null) {
            $context['response_code'] = $response->code;
        }

        $this->logWarning('Flare daemon delivery failed, falling back to direct delivery', $context);
    }

    /** @param array<string, mixed> $context */
    protected function logWarning(string $message, array $context = []): void
    {
        $normalizedContext = array_map(
            fn (mixed $value) => match (true) {
                $value instanceof Throwable => $value::class.': '.$value->getMessage(),
                is_scalar($value), $value === null => $value,
                default => get_debug_type($value),
            },
            $context,
        );

        $suffix = $normalizedContext === []
            ? ''
            : ' '.json_encode($normalizedContext, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        fwrite(STDERR, sprintf("[WARNING] %s%s\n", $message, $suffix));
    }
}
