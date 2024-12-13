<?php

namespace Spatie\FlareClient\AttributesProviders;

use RuntimeException;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\Support\Redactor;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Throwable;

class RequestAttributesProvider
{
    public function __construct(
        protected Redactor $redactor,
        protected UserAttributesProvider $userAttributesProvider
    ) {
    }

    public function toArray(Request $request): array
    {
        $user = $this->getUser($request);

        return [
            ...$this->getRequest($request),
            ...$this->getHeaders($request),
            ...$this->getCookies($request),
            ...$this->getSession($request),
            ...$user ? $this->userAttributesProvider->toArray($user) : [],
        ];
    }

    protected function getRequest(Request $request): array
    {
        $payload = [
            'url.full' => $request->getUri(),
            'url.scheme' => $request->getScheme(),
            'url.path' => $request->getPathInfo(),
            'url.query' => http_build_query($request->query->all()),

            'flare.entry_point.type' => EntryPointType::Web,
            'flare.entry_point.value' => $request->getUri(),
            'flare.entry_point.class' => null,

            'server.address' => empty($request->server->get('SERVER_NAME'))
                ? $request->server->get('SERVER_ADDR')
                : $request->server->get('SERVER_NAME'),
            'server.port' => $request->server->get('SERVER_PORT'),

            'user_agent.original' => $request->headers->get('User-Agent'),

            'http.request.method' => strtoupper($request->getMethod()),
            'http.request.body.size' => strlen($request->getContent()),
        ];

        $files = $this->mapFiles($request->files->all());

        if (! empty($files)) {
            $payload['http.request.files'] = $files;
        }

        if ($this->redactor->shouldCensorClientIps() === false) {
            $payload['client.address'] = $request->getClientIp();
        }

        $body = $this->redactor->censorBody(
            $this->getInputBag($request)->all() + $request->query->all()
        );

        if (! empty($body)) {
            $payload['http.request.body.contents'] = $body;
        }

        return $payload;
    }

    protected function getInputBag(Request $request): InputBag|ParameterBag
    {
        $contentType = $request->headers->get('CONTENT_TYPE') ?? 'text/html';

        $isJson = str_contains($contentType, '/json') || str_contains($contentType, '+json');

        if ($isJson) {
            return new InputBag((array) json_decode($request->getContent(), true));
        }

        return in_array($request->getMethod(), ['GET', 'HEAD'])
            ? $request->query
            : $request->request;
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

    protected function getSession(Request $request): array
    {
        try {
            $session = $request->getSession();
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

    protected function getCookies(Request $request): array
    {
        $cookies = $request->cookies->all();

        if (empty($cookies)) {
            return [];
        }

        return [
            'http.request.cookies' => $cookies,
        ];
    }

    protected function getHeaders(Request $request): array
    {
        $headers = $request->headers->all();

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

    protected function getUser(Request $request): mixed
    {
        return null;
    }
}
