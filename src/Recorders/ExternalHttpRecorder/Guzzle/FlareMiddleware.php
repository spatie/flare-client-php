<?php

namespace Spatie\FlareClient\Recorders\ExternalHttpRecorder\Guzzle;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Spatie\FlareClient\Flare;
use Throwable;

class FlareMiddleware
{
    public function __construct(protected Flare $flare)
    {
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $this->flare->externalHttp()->recordSending(
                url: (string) $request->getUri(),
                method: $request->getMethod(),
                bodySize: $request->getBody()->getSize() ?: 0,
                headers:$this->getHeaders($request),
            );

            return $handler($request, $options)->then(
                $this->onSuccess(),
                $this->onError()
            );
        };
    }

    protected function onSuccess(): callable
    {
        return function (ResponseInterface $response) {
            $this->flare->externalHttp()->recordReceived(
                responseCode: $response->getStatusCode(),
                responseBodySize: $response->getBody()->getSize() ?: 0,
                responseHeaders: $this->getHeaders($response),
            );

            return $response;
        };
    }

    protected function onError(): callable
    {
        return function (Throwable $reason) {
            if (method_exists($reason, 'getResponse') && $reason->getResponse() instanceof ResponseInterface) {
                $response = $reason->getResponse();

                $this->flare->externalHttp()->recordReceived(
                    responseCode: $response->getStatusCode(),
                    responseBodySize: $response->getBody()->getSize() ?: 0,
                    responseHeaders: $this->getHeaders($response),
                );

                throw $reason;
            }
            $errorMessage = $reason->getMessage();

            $this->flare->externalHttp()->recordConnectionFailed($errorMessage);

            throw $reason;
        };
    }

    protected function getHeaders(RequestInterface|ResponseInterface $requestResponse): array
    {
        $headers = [];

        foreach ($requestResponse->getHeaders() as $name => $value) {
            $headers[$name] = implode(', ', $value);

            if (empty($headers[$name])) {
                unset($headers[$name]);
            }
        }

        return $headers;
    }
}
