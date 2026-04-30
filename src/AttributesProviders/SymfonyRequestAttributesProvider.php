<?php

namespace Spatie\FlareClient\AttributesProviders;

use RuntimeException;
use Spatie\FlareClient\Contracts\RequestAttributesProvider;
use Spatie\FlareClient\Support\Redactor;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Throwable;

class SymfonyRequestAttributesProvider implements RequestAttributesProvider
{
    protected Request $request;

    public function __construct(
        protected Redactor $redactor,
        ?Request $request = null,
        protected bool $includeContents = true,
    ) {
        $this->request = $request ?? Request::createFromGlobals();
    }

    public function toArray(): array
    {
        $cookies = $this->redactor->shouldCensorCookies() ? [] : $this->getCookies();
        $session = $this->redactor->shouldCensorSession() ? [] : $this->getSession();

        return [
            ...$this->getRequest(),
            ...$this->getHeaders(),
            ...$cookies,
            ...$session,
        ];
    }

    public function url(): string
    {
        return $this->request->getUri();
    }

    public function path(): ?string
    {
        return $this->request->getPathInfo();
    }

    public function method(): string
    {
        return strtoupper($this->request->getMethod());
    }

    protected function getRequest(): array
    {
        $payload = [
            'url.full' => $this->request->getUri(),
            'url.scheme' => $this->request->getScheme(),
            'url.path' => $this->request->getPathInfo(),
            'url.query' => http_build_query($this->request->query->all()),

            'server.address' => empty($this->request->server->get('SERVER_NAME'))
                ? $this->request->server->get('SERVER_ADDR')
                : $this->request->server->get('SERVER_NAME'),
            'server.port' => $this->request->server->get('SERVER_PORT'),

            'user_agent.original' => $this->request->headers->get('User-Agent'),

            'http.request.method' => strtoupper($this->request->getMethod()),
            'http.request.body.size' => strlen($this->request->getContent()),
        ];

        $files = $this->mapFiles($this->request->files->all());

        if (! empty($files)) {
            $payload['http.request.files'] = $files;
        }

        if ($this->redactor->shouldCensorClientIps() === false) {
            $payload['client.address'] = $this->request->getClientIp();
        }

        $body = $this->redactor->censorBody(
            $this->getInputBag()->all() + $this->request->query->all()
        );

        if (! empty($body) && $this->includeContents) {
            $payload['http.request.body.contents'] = $body;
        }

        return $payload;
    }

    protected function getInputBag(): InputBag|ParameterBag
    {
        $contentType = $this->request->headers->get('CONTENT_TYPE') ?? 'text/html';

        $isJson = str_contains($contentType, '/json') || str_contains($contentType, '+json');

        if ($isJson) {
            return new InputBag((array) json_decode($this->request->getContent(), true));
        }

        return in_array($this->request->getMethod(), ['GET', 'HEAD'])
            ? $this->request->query
            : $this->request->request;
    }

    protected function mapFiles(array $files): array
    {
        return array_map(function ($file) {
            if (is_array($file)) {
                return $this->mapFiles($file);
            }

            if (! $file instanceof UploadedFile) {
                return [];
            }

            try {
                $fileSize = $file->getSize();
            } catch (RuntimeException $e) {
                $fileSize = 0;
            }

            try {
                $mimeType = $file->getMimeType();
            } catch (InvalidArgumentException $e) {
                $mimeType = 'undefined';
            }

            return [
                'path' => $file->getPathname(),
                'size' => $fileSize,
                'mime_type' => $mimeType,
            ];
        }, $files);
    }

    protected function getSession(): array
    {
        try {
            $session = $this->request->getSession();
        } catch (Throwable $exception) {
            return [];
        }

        if (! method_exists($session, 'all')) {
            return [];
        }

        $sessionEntries = $session->all();

        if (empty($sessionEntries)) {
            return [];
        }

        return [
            'http.request.session' => $sessionEntries,
        ];
    }

    protected function getCookies(): array
    {
        $cookies = $this->request->cookies->all();

        if (empty($cookies)) {
            return [];
        }

        return [
            'http.request.cookies' => $cookies,
        ];
    }

    protected function getHeaders(): array
    {
        $headers = $this->request->headers->all();

        foreach ($headers as $name => $value) {
            $headers[$name] = implode($value);

            if (empty($headers[$name])) {
                unset($headers[$name]);
            }
        }

        return [
            'http.request.headers' => $this->redactor->censorHeaders($headers),
        ];
    }
}
