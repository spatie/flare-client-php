<?php

namespace Spatie\FlareClient\Recorders\ExternalHttpRecorder\Guzzle;

use Closure;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Spatie\FlareClient\Flare;
use Throwable;

class FlareMiddleware
{
    public function __construct(protected Flare $flare)
    {
    }

    public function __invoke(callable $handler): Closure
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $this->flare->externalHttp()?->recordSending(
                url: (string) $request->getUri(),
                method: $request->getMethod(),
                bodySize: $request->getBody()->getSize() ?: 0,
                headers: $this->getHeaders($request),
            );

            try {
                return $handler($request, $options)->then(
                    $this->recordFulfilled(),
                    $this->recordRejected(),
                );
            } catch (Throwable $throwable) {
                $this->recordFailure($throwable);

                throw $throwable;
            }
        };
    }

    protected function recordFulfilled(): Closure
    {
        return function (ResponseInterface $response): ResponseInterface {
            $this->flare->externalHttp()?->recordReceived(
                responseCode: $response->getStatusCode(),
                responseBodySize: $this->getBodySize($response),
                responseHeaders: $this->getHeaders($response),
            );

            return $response;
        };
    }

    protected function recordRejected(): Closure
    {
        return function (mixed $reason): PromiseInterface {
            $this->recordFailure($reason);

            return Create::rejectionFor($reason);
        };
    }

    protected function recordFailure(mixed $reason): void
    {
        $errorType = get_debug_type($reason);

        $response = $this->responseFromFailure($reason);

        if ($response !== null) {
            $this->flare->externalHttp()?->recordReceived(
                responseCode: $response->getStatusCode(),
                responseBodySize: $this->getBodySize($response),
                responseHeaders: $this->getHeaders($response),
                errorType: $errorType,
            );

            return;
        }

        $this->flare->externalHttp()?->recordConnectionFailed($errorType);
    }

    protected function responseFromFailure(mixed $reason): ?ResponseInterface
    {
        if (! is_object($reason)) {
            return null;
        }

        if (! method_exists($reason, 'getResponse')) {
            return null;
        }

        $response = $reason->getResponse();

        return $response instanceof ResponseInterface ? $response : null;
    }

    protected function getBodySize(ResponseInterface $response): ?int
    {
        if ($response->getHeaderLine('Transfer-Encoding') === 'chunked') {
            return null;
        }

        if ($response->hasHeader('Content-Length')) {
            return (int) $response->getHeaderLine('Content-Length');
        }

        return $response->getBody()->getSize() ?: null;
    }

    protected function getHeaders(MessageInterface $message): array
    {
        $headers = [];

        foreach ($message->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);

            if (empty($headers[$name])) {
                unset($headers[$name]);
            }
        }

        return $headers;
    }
}
