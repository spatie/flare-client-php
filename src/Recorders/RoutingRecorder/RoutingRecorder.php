<?php

namespace Spatie\FlareClient\Recorders\RoutingRecorder;

use Spatie\FlareClient\AttributesProviders\PhpRouteAttributesProvider;
use Spatie\FlareClient\Contracts\EntryPointHandlerProvider;
use Spatie\FlareClient\Contracts\RouteAttributesProvider;
use Spatie\FlareClient\EntryPoint\EntryPointResolver;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\SpansRecorder;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\PatternMatcher;
use Spatie\FlareClient\Support\TimeInterval;
use Spatie\FlareClient\Tracer;

class RoutingRecorder extends SpansRecorder
{
    protected bool $beforeMiddleware = false;

    protected bool $globalBeforeMiddleware = false;

    protected bool $routing = false;

    protected bool $afterMiddleware = false;

    protected bool $globalAfterMiddleware = false;

    /** @var array<int, string> */
    protected array $ignoredRoutes = [];

    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        protected EntryPointResolver $entryPointResolver,
        array $config,
    ) {
        parent::__construct($tracer, $backTracer, $config);
    }

    protected function configure(array $config): void
    {
        if (! array_key_exists('with_traces', $config)) {
            $this->withTraces = true;
        }

        $this->ignoredRoutes = $config['ignored_routes'] ?? [];
    }

    public static function type(): string|RecorderType
    {
        return RecorderType::Routing;
    }

    public function recordGlobalBeforeMiddlewareStart(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        if ($this->globalBeforeMiddleware === true) {
            return null;
        }

        $this->globalBeforeMiddleware = true;

        return $this->startSpan(
            'Global Middleware (before)',
            attributes: [
                'flare.span_type' => SpanType::GlobalBeforeMiddleware,
                ...$attributes,
            ],
            time: $time
        );
    }

    public function recordGlobalBeforeMiddlewareEnd(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        if ($this->globalBeforeMiddleware === false) {
            return null;
        }

        $this->globalBeforeMiddleware = false;

        return $this->endSpan(
            time: $time,
            additionalAttributes: $attributes,
        );
    }

    public function recordGlobalBeforeMiddleware(
        array $attributes = [],
        ?int $start = null,
        ?int $end = null,
        ?int $duration = null,
    ): ?Span {
        [$start, $end] = TimeInterval::resolve($this->tracer->time, $start, $end, $duration);

        if ($this->recordGlobalBeforeMiddlewareStart($attributes, time: $start)) {
            return $this->recordGlobalBeforeMiddlewareEnd(time: $end);
        }

        return null;
    }

    public function recordRoutingStart(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        if ($this->routing === true) {
            return null;
        }

        if ($this->globalBeforeMiddleware) {
            $this->recordGlobalBeforeMiddlewareEnd(time: $time);
        }

        $this->routing = true;

        return $this->startSpan(
            'Routing',
            attributes: [
                'flare.span_type' => SpanType::Routing,
                ...$attributes,
            ],
            time: $time
        );
    }

    public function recordRoutingEnd(
        RouteAttributesProvider $routeAttributesProvider,
        array $attributes = [],
        ?int $time = null,
    ): ?Span {
        if ($this->routing === false) {
            return null;
        }

        $this->routing = false;

        $routeName = $routeAttributesProvider->route();

        $method = $routeAttributesProvider->method();
        $method = strtoupper($method);

        $entryPoint = $this->entryPointResolver->get();

        if (! $entryPoint->handlerResolved) {
            $entryPointProvider = $routeAttributesProvider instanceof EntryPointHandlerProvider
                ? $routeAttributesProvider
                : null;

            $entryPoint->setHandler(
                handlerIdentifier: $routeName ? "{$method} {$routeName}" : null,
                handlerName: $entryPointProvider?->entryPointHandlerName() ?? null,
                handlerType: $entryPointProvider?->entryPointHandlerType() ?? 'php_request',
            );
        }

        $this->tracer->reevaluateSampling();

        if ($routeName !== null && $this->shouldIgnoreRoute($routeName)) {
            $this->tracer->unsample();
        }

        if ($routeName !== null && ! array_key_exists('http.route', $attributes)) {
            $attributes['http.route'] = $routeName;
        }

        return $this->endSpan(
            time: $time,
            additionalAttributes: $attributes,
        );
    }

    public function recordRoutingEndFromDefined(
        string $route,
        ?string $method = null,
        ?string $handlerName = null,
        array $attributes = [],
        ?int $time = null,
    ): ?Span {
        return $this->recordRoutingEnd(
            new PhpRouteAttributesProvider($route, $method, $handlerName),
            $attributes,
            $time,
        );
    }

    protected function recordForcedRoutingEnd(
        array $attributes = [],
        ?int $time = null,
    ): ?Span {
        if ($this->routing === false) {
            return null;
        }

        $this->routing = false;

        return $this->endSpan(
            time: $time,
            additionalAttributes: $attributes,
        );
    }

    public function recordRouting(
        RouteAttributesProvider $routeAttributesProvider,
        array $attributes = [],
        ?int $start = null,
        ?int $end = null,
        ?int $duration = null,
    ): ?Span {
        [$start, $end] = TimeInterval::resolve($this->tracer->time, $start, $end, $duration);

        if (! $this->recordRoutingStart($attributes, time: $start)) {
            return null;
        }

        return $this->recordRoutingEnd($routeAttributesProvider, time: $end);
    }

    public function recordBeforeMiddlewareStart(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        if ($this->beforeMiddleware === true) {
            return null;
        }

        if ($this->globalBeforeMiddleware) {
            $this->recordGlobalBeforeMiddlewareEnd(time: $time);
        }

        if ($this->routing) {
            $this->recordForcedRoutingEnd(time: $time);
        }

        $this->beforeMiddleware = true;

        return $this->startSpan(
            'Middleware (before)',
            attributes: [
                'flare.span_type' => SpanType::BeforeMiddleware,
                ...$attributes,
            ],
            time: $time
        );
    }

    public function recordBeforeMiddlewareEnd(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        if ($this->beforeMiddleware === false) {
            return null;
        }

        $this->beforeMiddleware = false;

        return $this->endSpan(
            time: $time,
            additionalAttributes: $attributes,
        );
    }

    public function recordBeforeMiddleware(
        array $attributes = [],
        ?int $start = null,
        ?int $end = null,
        ?int $duration = null,
    ): ?Span {
        [$start, $end] = TimeInterval::resolve($this->tracer->time, $start, $end, $duration);

        if ($this->recordBeforeMiddlewareStart($attributes, time: $start)) {
            return $this->recordBeforeMiddlewareEnd(time: $end);
        }

        return null;
    }

    public function recordAfterMiddlewareStart(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        if ($this->afterMiddleware === true) {
            return null;
        }

        if ($this->globalBeforeMiddleware) {
            $this->recordGlobalBeforeMiddlewareEnd(time: $time);
        }

        if ($this->beforeMiddleware) {
            $this->recordBeforeMiddlewareEnd(time: $time);
        }

        $this->afterMiddleware = true;

        return $this->startSpan(
            'Middleware (after)',
            attributes: [
                'flare.span_type' => SpanType::AfterMiddleware,
                ...$attributes,
            ],
            time: $time
        );
    }

    public function recordAfterMiddlewareEnd(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        if ($this->afterMiddleware === false) {
            return null;
        }

        $this->afterMiddleware = false;

        return $this->endSpan(
            time: $time,
            additionalAttributes: $attributes,
        );
    }

    public function recordAfterMiddleware(
        array $attributes = [],
        ?int $start = null,
        ?int $end = null,
        ?int $duration = null,
    ): ?Span {
        [$start, $end] = TimeInterval::resolve($this->tracer->time, $start, $end, $duration);

        if ($this->recordAfterMiddlewareStart($attributes, time: $start)) {
            return $this->recordAfterMiddlewareEnd(time: $end);
        }

        return null;
    }

    public function recordGlobalAfterMiddlewareStart(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        if ($this->globalAfterMiddleware === true) {
            return null;
        }

        if ($this->globalBeforeMiddleware) {
            $this->recordGlobalBeforeMiddlewareEnd(time: $time);
        }

        if ($this->beforeMiddleware) {
            $this->recordBeforeMiddlewareEnd(time: $time);
        }

        if ($this->afterMiddleware) {
            $this->recordAfterMiddlewareEnd(time: $time);
        }

        $this->globalAfterMiddleware = true;

        return $this->startSpan(
            'Global Middleware (after)',
            attributes: [
                'flare.span_type' => SpanType::GlobalAfterMiddleware,
                ...$attributes,
            ],
            time: $time
        );
    }

    public function recordGlobalAfterMiddlewareEnd(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        if ($this->globalAfterMiddleware === false) {
            return null;
        }

        $this->globalAfterMiddleware = false;

        return $this->endSpan(
            time: $time,
            additionalAttributes: $attributes,
        );
    }

    public function recordGlobalAfterMiddleware(
        array $attributes = [],
        ?int $start = null,
        ?int $end = null,
        ?int $duration = null,
    ): ?Span {
        [$start, $end] = TimeInterval::resolve($this->tracer->time, $start, $end, $duration);

        if ($this->recordGlobalAfterMiddlewareStart($attributes, time: $start)) {
            return $this->recordGlobalAfterMiddlewareEnd(time: $end);
        }

        return null;
    }

    protected function shouldIgnoreRoute(string $route): bool
    {
        return PatternMatcher::matchesAny($route, [...$this->ignoredRoutes, ...$this->defaultIgnoredRoutes()]);
    }

    /** @return array<int, string> */
    protected function defaultIgnoredRoutes(): array
    {
        return [];
    }
}
