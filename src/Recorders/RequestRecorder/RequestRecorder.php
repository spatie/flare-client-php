<?php

namespace Spatie\FlareClient\Recorders\RequestRecorder;

use Spatie\FlareClient\AttributesProviders\PhpRequestAttributesProvider;
use Spatie\FlareClient\AttributesProviders\PhpResponseAttributesProvider;
use Spatie\FlareClient\AttributesProviders\SymfonyRequestAttributesProvider;
use Spatie\FlareClient\AttributesProviders\SymfonyResponseAttributesProvider;
use Spatie\FlareClient\AttributesProviders\UserAttributesProvider;
use Spatie\FlareClient\Contracts\RequestAttributesProvider;
use Spatie\FlareClient\Contracts\ResponseAttributesProvider;
use Spatie\FlareClient\Contracts\RouteAttributesProvider;
use Spatie\FlareClient\EntryPoint\EntryPointResolver;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\SpansRecorder;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\PatternMatcher;
use Spatie\FlareClient\Support\Redactor;
use Spatie\FlareClient\Tracer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestRecorder extends SpansRecorder
{
    /** @var array<int, string> */
    protected array $ignoredUrls = [];

    /** @var array<int, string> */
    protected array $ignoredPaths = [];

    public static function type(): string|RecorderType
    {
        return RecorderType::Request;
    }

    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        protected Redactor $redactor,
        protected EntryPointResolver $entryPointResolver,
        array $config,
    ) {
        parent::__construct($tracer, $backTracer, $config);
    }

    protected function configure(array $config): void
    {
        $this->withTraces = true;
        $this->withErrors = false;

        $this->ignoredUrls = $config['ignored_urls'] ?? [];
        $this->ignoredPaths = $config['ignored_paths'] ?? [];
    }

    public function recordStart(
        RequestAttributesProvider $requestAttributesProvider,
        array $attributes = [],
    ): ?Span {
        $url = $requestAttributesProvider->url();
        $path = $requestAttributesProvider->path();

        if ($this->shouldIgnoreUrl($url) || $this->shouldIgnorePath($path)) {
            $this->tracer->unsample();

            return null;
        }

        return $this->startSpan(nameAndAttributes: function () use ($requestAttributesProvider, $attributes) {
            $name = $requestAttributesProvider->path() ?? $requestAttributesProvider->url();

            return [
                'name' => "Request - {$name}",
                'attributes' => [
                    'flare.span_type' => SpanType::Request,
                    ...$this->entryPointResolver->get()->toAttributes(),
                    ...$attributes,
                ],
            ];
        });
    }

    public function recordStartFromSymfonyRequest(
        Request $request,
        array $attributes = [],
    ): ?Span {
        return $this->recordStart(
            new SymfonyRequestAttributesProvider($this->redactor, $request, includeContents: false),
            $attributes,
        );
    }

    public function recordStartFromGlobals(
        array $attributes = [],
    ): ?Span {
        return $this->recordStart(
            new PhpRequestAttributesProvider($this->redactor),
            $attributes,
        );
    }

    public function recordEnd(
        ?RequestAttributesProvider $requestAttributesProvider = null,
        ?ResponseAttributesProvider $responseAttributesProvider = null,
        ?RouteAttributesProvider $routeAttributesProvider = null,
        ?UserAttributesProvider $userAttributesProvider = null,
        array $attributes = [],
    ): ?Span {
        $route = $routeAttributesProvider?->route();

        return $this->endSpan(
            additionalAttributes: [
                ...$this->entryPointResolver->get()->toAttributes(),
                ...($requestAttributesProvider?->toArray() ?? []),
                ...($routeAttributesProvider?->toArray() ?? []),
                ...($userAttributesProvider?->toArray() ?? []),
                ...($responseAttributesProvider?->toArray() ?? []),
                ...$attributes,
            ],
            spanCallback: $route !== null
                ? fn (Span $span) => $span->updateName("Request - {$route}")
                : null,
            includeMemoryUsage: true,
        );
    }

    public function recordEndFromSymfonyResponse(
        Response $response,
        ?Request $request = null,
        ?RouteAttributesProvider $routeAttributesProvider = null,
        ?UserAttributesProvider $userAttributesProvider = null,
        array $attributes = [],
    ): ?Span {
        return $this->recordEnd(
            requestAttributesProvider: $request !== null
                ? new SymfonyRequestAttributesProvider($this->redactor, $request, includeContents: false)
                : null,
            responseAttributesProvider: new SymfonyResponseAttributesProvider($this->redactor, $response),
            routeAttributesProvider: $routeAttributesProvider,
            userAttributesProvider: $userAttributesProvider,
            attributes: $attributes,
        );
    }

    /** @param array<string, string> $headers */
    public function recordEndFromDefined(
        ?int $statusCode = null,
        ?int $bodySize = null,
        array $headers = [],
        array $attributes = [],
    ): ?Span {
        return $this->recordEnd(
            responseAttributesProvider: new PhpResponseAttributesProvider($this->redactor, $statusCode, $bodySize, $headers),
            attributes: $attributes,
        );
    }

    protected function shouldIgnoreUrl(string $url): bool
    {
        return PatternMatcher::matchesAny($url, [...$this->ignoredUrls, ...$this->defaultIgnoredUrls()]);
    }

    protected function shouldIgnorePath(?string $path): bool
    {
        if ($path === null) {
            return false;
        }

        return PatternMatcher::matchesAny($path, [...$this->ignoredPaths, ...$this->defaultIgnoredPaths()]);
    }

    /** @return array<int, string> */
    protected function defaultIgnoredUrls(): array
    {
        return [];
    }

    /** @return array<int, string> */
    protected function defaultIgnoredPaths(): array
    {
        return [];
    }
}
