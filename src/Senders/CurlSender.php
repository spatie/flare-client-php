<?php

namespace Spatie\FlareClient\Senders;

use Spatie\FlareClient\Http\Exceptions\BadResponseCode;
use Spatie\FlareClient\Http\Exceptions\InvalidData;
use Spatie\FlareClient\Http\Exceptions\NotFound;
use Spatie\FlareClient\Http\Response;

class CurlSender implements Sender
{
    public function post(string $endpoint, string $apiToken, array $payload): array
    {
        $queryString = http_build_query([
            'key' => $apiToken,
        ]);

        $fullUrl = "{$endpoint}?{$queryString}";

        $headers = [
            'x-api-token: '.$apiToken,
        ];

        $curlHandle = $this->getCurlHandle($fullUrl, $headers);

        $this->attachRequestPayload($curlHandle, $payload);

        $body = json_decode(curl_exec($curlHandle), true);

        $headers = curl_getinfo($curlHandle);
        $error = curl_error($curlHandle);

        $response = new Response($headers, $body, $error);

        if ($response->getHttpResponseCode() === 422) {
            throw InvalidData::createForResponse($response);
        }

        if ($response->getHttpResponseCode() === 404) {
            throw NotFound::createForResponse($response);
        }

        if ($response->getHttpResponseCode() !== 200 && $response->getHttpResponseCode() !== 204) {
            throw BadResponseCode::createForResponse($response);
        }

        return $response->getBody();
    }

    protected function getCurlHandle(string $fullUrl, array $headers = [])
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

    protected function attachRequestPayload(&$curlHandle, array $data)
    {
        $encoded = json_encode($data);

        $this->lastRequest['body'] = $encoded;
        curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $encoded);
    }
}
