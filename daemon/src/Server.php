<?php

namespace Spatie\FlareDaemon;

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Http\Middleware\LimitConcurrentRequestsMiddleware;
use React\Http\Middleware\RequestBodyBufferMiddleware;
use React\Http\Middleware\StreamingRequestMiddleware;
use React\Promise\PromiseInterface;
use React\Socket\SocketServer;
use Spatie\FlareDaemon\Support\Json;
use Spatie\FlareDaemon\Support\Output;
use RuntimeException;

class Server
{
    protected HttpServer $httpServer;

    protected ?SocketServer $socketServer = null;

    protected bool $acceptingRequests = true;

    public function __construct(
        protected LoopInterface $loop,
        protected Ingest $ingest,
        protected Output $output,
        protected string $listenAddress = '127.0.0.1:8787',
        protected int $maxRequestBytes = 2097152,
    ) {
        $this->httpServer = new HttpServer(
            new StreamingRequestMiddleware(),
            new LimitConcurrentRequestsMiddleware(100),
            new RequestBodyBufferMiddleware($this->maxRequestBytes),
            fn (ServerRequestInterface $request) => $this->handle($request),
        );
    }

    public function listen(): void
    {
        if ($this->socketServer !== null) {
            return;
        }

        try {
            $this->socketServer = new SocketServer($this->listenAddress, [], $this->loop);
        } catch (RuntimeException $e) {
            $port = $this->parsePort();

            $this->output->error(
                "Port {$port} is already in use. Run \"lsof -i :{$port} -sTCP:LISTEN\" to find the process, or use a different port with FLARE_DAEMON_LISTEN=127.0.0.1:9999",
            );

            throw $e;
        }

        $this->httpServer->listen($this->socketServer);

        $this->output->info('listening for local requests', [
            'address' => str_replace('tcp://', '', (string) $this->socketServer->getAddress()),
        ]);
    }

    protected function parsePort(): string
    {
        if (preg_match('/:(\d+)$/', $this->listenAddress, $matches)) {
            return $matches[1];
        }

        return $this->listenAddress;
    }

    public function stop(): void
    {
        $this->acceptingRequests = false;
        $this->socketServer?->close();
        $this->socketServer = null;
    }

    public function close(): void
    {
        $this->stop();
    }

    /**
     * @return Response|PromiseInterface<Response>
     */
    public function handle(ServerRequestInterface $request): Response|PromiseInterface
    {
        if (! $this->acceptingRequests) {
            return $this->jsonResponse(503, ['message' => 'Daemon is shutting down']);
        }

        $method = strtoupper($request->getMethod());
        $path = $request->getUri()->getPath();

        if ($path === '/health') {
            return $method === 'GET'
                ? $this->jsonResponse(200, ['status' => 'ok'])
                : $this->plainResponse(405, 'Method Not Allowed');
        }

        if ($path === '/status') {
            return $method === 'GET'
                ? $this->jsonResponse(200, $this->ingest->status())
                : $this->plainResponse(405, 'Method Not Allowed');
        }

        if (! preg_match('/^\/v1\/(errors|traces|logs)$/', $path, $matches)) {
            return $this->plainResponse(404, 'Not Found');
        }

        if ($method !== 'POST') {
            return $this->plainResponse(405, 'Method Not Allowed');
        }

        $apiKey = $request->getHeaderLine('x-api-token');

        if ($apiKey === '') {
            return $this->jsonResponse(422, ['message' => 'Missing API key']);
        }

        $body = (string) $request->getBody();

        try {
            $payload = Json::decode($body);
        } catch (RuntimeException) {
            return $this->jsonResponse(422, ['message' => 'Invalid JSON']);
        }

        if (! is_array($payload)) {
            return $this->jsonResponse(422, ['message' => 'Invalid JSON']);
        }

        $test = $request->getHeaderLine('x-flare-test') === '1';

        if (! $test) {
            $this->ingest->accept($apiKey, $matches[1], $payload);

            return $this->jsonResponse(202, ['status' => 'accepted']);
        }

        return $this->ingest->diagnose($apiKey, $matches[1], $payload)->then(function (array $result) {
            $headers = $result['headers'];
            $body = $result['body'];

            if (is_array($body)) {
                return $this->jsonResponse($result['status'], $body, $headers);
            }

            return new Response(
                $result['status'],
                array_merge(['Content-Type' => 'text/plain'], $headers),
                $body ?? '',
            );
        });
    }

    /**
     * @param array<string, string> $headers
     */
    protected function jsonResponse(int $status, mixed $payload, array $headers = []): Response
    {
        return new Response(
            $status,
            array_merge(['Content-Type' => 'application/json'], $headers),
            Json::encode($payload),
        );
    }

    protected function plainResponse(int $status, string $body): Response
    {
        return new Response($status, ['Content-Type' => 'text/plain'], $body);
    }
}
