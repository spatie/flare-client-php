<?php

namespace Spatie\FlareClient\AttributesProviders;

use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Throwable;

class RequestAttributesProvider
{
    /**
     * @param array<string> $censorBodyFields
     * @param array<string> $censorRequestHeaders
     * @param bool $removeIp
     */
    public function __construct(
        public array $censorBodyFields = [],
        public array $censorRequestHeaders = [],
        public bool $removeIp = false,
    ) {
        $this->censorBodyFields = array_map(
            fn (string $field) => mb_strtolower($field),
            $this->censorBodyFields
        );

        $this->censorRequestHeaders = array_map(
            fn (string $header) => strtr(
                $header,
                '_ABCDEFGHIJKLMNOPQRSTUVWXYZ', // HeaderBag::UPPER
                '-abcdefghijklmnopqrstuvwxyz' // HeaderBag::LOWER
            ),
            $this->censorRequestHeaders
        );
    }

    public function toArray(Request $request): array
    {
        return [
            ...$this->getRequest($request),
            ...$this->getHeaders($request),
            ...$this->getCookies($request),
            ...$this->getSession($request),
        ];
    }

    protected function getRequest(Request $request): array
    {
        $payload = [
            'url.full' => $request->getUri(),
            'url.scheme' => $request->getScheme(),
            'url.path' => $request->getPathInfo(),
            'url.query' => http_build_query($request->query->all()),

            'server.address' => empty($request->server->get('SERVER_NAME'))
                ? $request->server->get('SERVER_ADDR')
                : $request->server->get('SERVER_NAME'),
            'server.port' => $request->server->get('SERVER_PORT'),

            'user_agent.original' => $request->headers->get('User-Agent'),

            'http.request.method' => strtoupper($request->getMethod()),
            'http.request.files' => $this->getFiles($request),
            'http.request.body.size' => strlen($request->getContent()),
        ];

        if ($this->removeIp === false) {
            $payload['client.address'] = $request->getClientIp();
        }

        $body = $this->getInputBag($request)->all() + $request->query->all();

        foreach ($body as $key => $value) {
            $value = in_array(mb_strtolower($key), $this->censorBodyFields)
                ? '<CENSORED>'
                : $value;

            $payload['http.request.body.contents'][$key] = $value;
        }

        return $payload;
    }

    protected function getInputBag(Request $request): InputBag|ParameterBag
    {
        $contentType = $request->headers->get('CONTENT_TYPE', 'text/html');

        $isJson = str_contains($contentType, '/json') || str_contains($contentType, '+json');

        if ($isJson) {
            return new InputBag((array) json_decode($request->getContent(), true));
        }

        return in_array($request->getMethod(), ['GET', 'HEAD'])
            ? $request->query
            : $request->request;
    }

    protected function getFiles(Request $request): array
    {
        if (is_null($request->files)) {
            return [];
        }

        return $this->mapFiles($request->files->all());
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
                'pathname' => $file->getPathname(),
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

        try {
            $session = json_encode($session->all());

            return [
                'http.request.session' => $session,
            ];
        } catch (Throwable $e) {
            return [];
        }
    }

    protected function getCookies(Request $request): array
    {
        return [
            'http.request.cookies' => $request->cookies->all(),
        ];
    }

    protected function getHeaders(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $value) {
            $name = mb_strtolower($name);

            $value = in_array($name, $this->censorRequestHeaders)
                ? '<CENSORED>'
                : implode($value);

            if (empty($value)) {
                continue;
            }

            /** @var $value list<string|null> */
            $headers['http.request.headers'][$name] = $value;
        }

        return $headers;
    }
}
