<?php

namespace Spatie\FlareClient\Senders;

use Closure;
use CurlHandle;
use Spatie\FlareClient\Senders\Exceptions\ConnectionError;
use Spatie\FlareClient\Senders\Support\Response;

class CurlSender implements Sender
{
    protected int $timeout;

    public function __construct(
        protected array $config = []
    ) {
        $this->timeout = $this->config['timeout'] ?? 10;
    }

    public function post(string $endpoint, string $apiToken, array $payload, Closure $callback): void
    {
        $queryString = http_build_query([
            'key' => $apiToken,
        ]);

        $fullUrl = "{$endpoint}?{$queryString}";

        $headers = [
            'x-api-token: '.$apiToken,
        ];

        $curlHandle = $this->getCurlHandle($fullUrl, $headers);

        $encoded = json_encode($payload);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ConnectionError('Invalid JSON payload provided');
        }

        curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $encoded);

        $json = curl_exec($curlHandle);

        if (is_bool($json)) {
            throw new ConnectionError(curl_error($curlHandle));
        }

        $body = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ConnectionError('Invalid JSON response received');
        }

        $headers = curl_getinfo($curlHandle);
        $error = curl_error($curlHandle);

        if ($error) {
            throw new ConnectionError($error);
        }

        $callback(new Response($headers['http_code'], $body));
    }

    protected function getCurlHandle(string $fullUrl, array $headers = []): CurlHandle
    {
        $curlHandle = curl_init();

        curl_setopt($curlHandle, CURLOPT_URL, $fullUrl);

        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, array_merge([
            'Accept: application/json',
            'Content-Type: application/json',
        ], $headers));

        curl_setopt($curlHandle, CURLOPT_USERAGENT, 'Laravel/Flare API 1.0');
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curlHandle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($curlHandle, CURLOPT_ENCODING, '');
        curl_setopt($curlHandle, CURLINFO_HEADER_OUT, true);
        curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curlHandle, CURLOPT_MAXREDIRS, 1);

        curl_setopt($curlHandle, CURLOPT_POST, true);

        return $curlHandle;
    }
}
