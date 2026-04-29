<?php

namespace Spatie\FlareClient\Recorders\RequestRecorder;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\AttributesProviders\RequestAttributesProvider;
use Spatie\FlareClient\AttributesProviders\ResponseAttributesProvider;
use Spatie\FlareClient\EntryPoint\EntryPointResolver;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\SpansRecorder;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\PatternMatcher;
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

    public static function register(ContainerInterface $container, array $config): Closure
    {
        return fn () => new self(
            $container->get(Tracer::class),
            $container->get(BackTracer::class),
            $config,
            $container->get(RequestAttributesProvider::class),
            $container->get(ResponseAttributesProvider::class),
            $container->get(EntryPointResolver::class),
        );
    }

    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        array $config,
        protected RequestAttributesProvider $requestAttributesProvider,
        protected ResponseAttributesProvider $responseAttributesProvider,
        protected EntryPointResolver $entryPointResolver,
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
        ?Request $request = null,
        ?string $route = null,
        array $attributes = [],
    ): ?Span {
        $request ??= Request::createFromGlobals();

        if ($this->shouldIgnoreUrl($request->getPathInfo())) {
            $this->tracer->unsample();

            return null;
        }

        return $this->startSpan(nameAndAttributes: function () use ($route, $request, $attributes) {
            $requestAttributes = $this->requestAttributesProvider->toArray(
                $request,
                includeContents: false,
            );

            if ($route) {
                $requestAttributes['http.route'] = $route;
            }

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
        array $attributes = [],
    ): ?Span {
        $responseAttributes = $response ? $this->responseAttributesProvider->toArray($response) : [];

        return $this->endSpan(additionalAttributes: [
            ...$this->entryPointResolver->get()->toAttributes(),
            ...$responseAttributes,
            ...$attributes,
        ], includeMemoryUsage: true);
    }
}
