<?php

namespace Spatie\FlareClient\Recorders\ExternalHttpRecorder;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\Recorder;
use Spatie\FlareClient\Recorders\SpansRecorder;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\Redactor;
use Spatie\FlareClient\Tracer;

class ExternalHttpRecorder extends SpansRecorder
{
    public static function register(ContainerInterface $container, array $config): Closure
    {
        return fn () => new self(
            $container->get(Tracer::class),
            $container->get(BackTracer::class),
            $config,
            $container->get(Redactor::class),
        );
    }

    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        array $config,
        protected Redactor $redactor,
    ) {
        parent::__construct($tracer, $backTracer, $config);
    }

    public static function type(): RecorderType
    {
        return RecorderType::ExternalHttp;
    }

    public function recordSending(
        string $url,
        string $method,
        ?int $bodySize = null,
        array $headers = [],
        array $attributes = [],
    ): ?Span {
        $parsedUrl = parse_url($url);

        return $this->startSpan(
            name: fn () => is_array($parsedUrl) && array_key_exists('host', $parsedUrl)
                ? "Http Request - {$parsedUrl['host']}"
                : 'Http Request',
            attributes: fn () => [
                'flare.span_type' => SpanType::HttpRequest,
                'url.full' => $url,
                'http.request.method' => $method,
                'server.address' => $parsedUrl['host'] ?? null,
                'server.port' => $parsedUrl['port'] ?? null,
                'url.scheme' => $parsedUrl['scheme'] ?? null,
                'url.path' => $parsedUrl['path'] ?? null,
                'url.query' => $parsedUrl['query'] ?? null,
                'url.fragment' => $parsedUrl['fragment'] ?? null,
                'http.request.body.size' => $bodySize,
                'http.request.headers' => $this->redactor->censorHeaders($headers),
                ...$attributes,
            ],
        );
    }

    public function recordReceived(
        int $responseCode,
        ?int $responseBodySize = null,
        array $responseHeaders = [],
    ): ?Span {
        return $this->endSpan(additionalAttributes:  [
            'http.response.status_code' => $responseCode,
            'http.response.body.size' => $responseBodySize,
            'http.response.headers' => $this->redactor->censorHeaders($responseHeaders),
        ]);
    }

    public function recordConnectionFailed(
        string $errorType
    ): ?Span {
        return $this->endSpan(additionalAttributes:  [
            'error.type' => $errorType,
        ]);
    }
}
