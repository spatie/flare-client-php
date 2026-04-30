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
    }

    public function recordStart(
        RequestAttributesProvider $requestAttributesProvider,
        ?RouteAttributesProvider $routeAttributesProvider = null,
        ?UserAttributesProvider $userAttributesProvider = null,
        array $attributes = [],
    ): ?Span {
        $url = $requestAttributesProvider->url();

        if ($this->shouldIgnoreUrl($url)) {
            $this->tracer->unsample();

            return null;
        }

        return $this->startSpan(nameAndAttributes: function () use ($requestAttributesProvider, $routeAttributesProvider, $userAttributesProvider, $attributes) {
            $name = $routeAttributesProvider?->route() ?? $requestAttributesProvider->path() ?? $requestAttributesProvider->url();

            return [
                'name' => "Request - {$name}",
                'attributes' => [
                    'flare.span_type' => SpanType::Request,
                    ...$this->entryPointResolver->get()->toAttributes(),
                    ...$requestAttributesProvider->toArray(),
                    ...($routeAttributesProvider?->toArray() ?? []),
                    ...($userAttributesProvider?->toArray() ?? []),
                    ...$attributes,
                ],
            ];
        });
    }

    public function recordStartFromSymfonyRequest(
        Request $request,
        ?RouteAttributesProvider $route = null,
        ?UserAttributesProvider $user = null,
        array $attributes = [],
    ): ?Span {
        return $this->recordStart(
            new SymfonyRequestAttributesProvider($this->redactor, $request, includeContents: false),
            $route,
            $user,
            $attributes,
        );
    }

    public function recordStartFromGlobals(
        ?RouteAttributesProvider $route = null,
        ?UserAttributesProvider $user = null,
        array $attributes = [],
    ): ?Span {
        return $this->recordStart(
            new PhpRequestAttributesProvider($this->redactor),
            $route,
            $user,
            $attributes,
        );
    }

    public function recordEnd(
        ?ResponseAttributesProvider $responseAttributesProvider = null,
        array $attributes = [],
    ): ?Span {
        return $this->endSpan(additionalAttributes: [
            ...$this->entryPointResolver->get()->toAttributes(),
            ...($responseAttributesProvider?->toArray() ?? []),
            ...$attributes,
        ], includeMemoryUsage: true);
    }

    public function recordEndFromSymfonyResponse(
        Response $response,
        array $attributes = [],
    ): ?Span {
        return $this->recordEnd(
            new SymfonyResponseAttributesProvider($this->redactor, $response),
            $attributes,
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
            new PhpResponseAttributesProvider($this->redactor, $statusCode, $bodySize, $headers),
            $attributes,
        );
    }

    protected function shouldIgnoreUrl(string $path): bool
    {
        return PatternMatcher::matchesAny($path, [...$this->ignoredUrls, ...$this->defaultIgnoredUrls()]);
    }

    /** @return array<int, string> */
    protected function defaultIgnoredUrls(): array
    {
        return [];
    }
}
