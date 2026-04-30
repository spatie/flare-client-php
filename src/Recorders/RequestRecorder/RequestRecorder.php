<?php

namespace Spatie\FlareClient\Recorders\RequestRecorder;

use Spatie\FlareClient\AttributesProviders\RequestAttributesProvider;
use Spatie\FlareClient\AttributesProviders\ResponseAttributesProvider;
use Spatie\FlareClient\Contracts\AttributesProvider;
use Spatie\FlareClient\Contracts\EntryPointHandlerProvider;
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

    /** @var class-string<RequestAttributesProvider> */
    protected string $requestAttributesProvider;

    /** @var class-string<ResponseAttributesProvider> */
    protected string $responseAttributesProvider;

    /** @var class-string<\Spatie\FlareClient\AttributesProviders\UserAttributesProvider> */
    protected string $userAttributesProvider;

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
        $this->requestAttributesProvider = $config['request_attributes_provider'] ?? RequestAttributesProvider::class;
        $this->responseAttributesProvider = $config['response_attributes_provider'] ?? ResponseAttributesProvider::class;
        $this->userAttributesProvider = $config['user_attributes_provider'] ?? \Spatie\FlareClient\AttributesProviders\EmptyUserAttributesProvider::class;
    }

    public function recordStart(
        ?Request $request = null,
        ?string $route = null,
        ?AttributesProvider $provider = null,
        array $attributes = [],
    ): ?Span {
        $request ??= Request::createFromGlobals();

        if ($this->shouldIgnoreUrl($request->getPathInfo())) {
            $this->tracer->unsample();

            return null;
        }

        $provider ??= new $this->requestAttributesProvider(
            $this->redactor,
            $this->userAttributesProvider,
            $request,
            includeContents: false,
        );

        return $this->startSpan(nameAndAttributes: function () use ($route, $provider, $attributes) {
            $requestAttributes = $provider->toArray();

            if ($route) {
                $requestAttributes['http.route'] = $route;
            }

            $this->resolveEntryPointHandler($provider);

            return [
                'name' => "Request - {$requestAttributes['url.full']}",
                'attributes' => [
                    'flare.span_type' => SpanType::Request,
                    ...$this->entryPointResolver->get()->toAttributes(),
                    ...$requestAttributes,
                    ...$attributes,
                ],
            ];
        });
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

    public function recordEnd(
        ?Response $response = null,
        ?AttributesProvider $provider = null,
        array $attributes = [],
    ): ?Span {
        if ($provider === null && $response !== null) {
            $provider = new $this->responseAttributesProvider($this->redactor, $response);
        }

        $responseAttributes = $provider?->toArray() ?? [];

        return $this->endSpan(additionalAttributes: [
            ...$this->entryPointResolver->get()->toAttributes(),
            ...$responseAttributes,
            ...$attributes,
        ], includeMemoryUsage: true);
    }

    protected function resolveEntryPointHandler(AttributesProvider $provider): void
    {
        if (! $provider instanceof EntryPointHandlerProvider) {
            return;
        }

        $entryPoint = $this->entryPointResolver->get();

        if ($entryPoint->handlerResolved) {
            return;
        }

        $entryPoint->setHandler(
            handlerIdentifier: $provider->entryPointHandlerIdentifier() ?? '',
            handlerName: $provider->entryPointHandlerName(),
            handlerType: $provider->entryPointHandlerType(),
        );
    }
}
