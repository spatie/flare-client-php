<?php

namespace Spatie\FlareClient\Recorders\ExternalHttpRecorder;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Concerns\Recorders\RecordsPendingSpans;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\Redactor;
use Spatie\FlareClient\Tracer;

class ExternalHttpRecorder implements SpansRecorder
{
    /** @use RecordsPendingSpans<Span> */
    use RecordsPendingSpans;

    public function __construct(
        protected Tracer $tracer,
        protected BackTracer $backTracer,
        protected array $config,
        protected Redactor $redactor,
    )
    {
        $this->configure($config);
    }

    public static function register(ContainerInterface $container, array $config): Closure
    {
        return fn () => new self(
            $container->get(Tracer::class),
            $container->get(BackTracer::class),
            $config,
            $container->get(Redactor::class),
        );
    }

    public static function type(): RecorderType
    {
        return RecorderType::ExternalHttp;
    }

    public function recordSending(
        string $url,
        string $method,
        int $requestBodySize,
        array $headers = [],
    ): ?Span {
        return $this->startSpan(function () use ($headers, $requestBodySize, $method, $url) {
            $parsedUrl = parse_url($url);

            $name = is_array($parsedUrl) && array_key_exists('host', $parsedUrl)
                ? "Http Request - {$parsedUrl['host']}"
                : 'Http Request';

            return Span::build(
                traceId: $this->tracer->currentTraceId() ?? '',
                parentId: $this->tracer->currentSpanId(),
                name: $name,
                attributes: [
                    'flare.span_type' => SpanType::HttpRequest,
                    'url.full' => $url,
                    'http.request.method' => $method,
                    'server.address' => $parsedUrl['host'] ?? null,
                    'server.port' => $parsedUrl['port'] ?? null,
                    'url.scheme' => $parsedUrl['scheme'] ?? null,
                    'url.path' => $parsedUrl['path'] ?? null,
                    'url.query' => $parsedUrl['query'] ?? null,
                    'url.fragment' => $parsedUrl['fragment'] ?? null,
                    'http.request.body.size' => $requestBodySize,
                    'http.request.headers' => $this->redactor->censorHeaders($headers),
                ],
            );
        });
    }

    public function recordReceived(
        int $responseCode,
        int $responseBodySize,
        array $headers = [],
    ): ?Span {
        return $this->endSpan(attributes: [
            'http.response.status_code' => $responseCode,
            'http.response.body.size' => $responseBodySize,
            'http.response.headers' => $this->redactor->censorHeaders($headers),
        ]);
    }

    public function recordConnectionFailed(
        string $errorType
    ): ?Span {
        return $this->endSpan(attributes: [
            'error.type' => $errorType,
        ]);
    }
}
