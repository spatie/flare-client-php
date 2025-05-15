<?php

namespace Spatie\FlareClient\Recorders\RequestRecorder;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\AttributesProviders\RequestAttributesProvider;
use Spatie\FlareClient\AttributesProviders\UserAttributesProvider;
use Spatie\FlareClient\Concerns\Recorders\RecordsSpans;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\Redactor;
use Spatie\FlareClient\Tracer;
use Symfony\Component\HttpFoundation\Request;

class RequestRecorder implements SpansRecorder
{
    /** @use RecordsSpans<Span> */
    use RecordsSpans;

    public static function type(): string|RecorderType
    {
        return RecorderType::Request;
    }

    public function __construct(
        protected Tracer $tracer,
        protected BackTracer $backTracer,
        protected array $config,
        protected RequestAttributesProvider $requestAttributesProvider,
    ) {
        $this->configure($config);
    }

    protected function configure(array $config): void
    {
        $this->withTraces = true;
    }

    public static function register(ContainerInterface $container, array $config): Closure
    {
        return fn () => new self(
            $container->get(Tracer::class),
            $container->get(BackTracer::class),
            $config,
            new RequestAttributesProvider(
                $container->get(Redactor::class),
                $container->get(UserAttributesProvider::class)
            )
        );
    }

    public function recordStart(
        ?Request $request = null,
        ?string $route = null,
        ?string $entryPointClass = null,
        array $attributes = [],
    ): ?Span {
        return $this->startSpan(nameAndAttributes: function () use ($entryPointClass, $route, $request, $attributes) {
            $requestAttributes = $this->requestAttributesProvider->toArray(
                $request ?? Request::createFromGlobals()
            );

            if ($route) {
                $requestAttributes['http.route'] = $route;
            }

            if ($entryPointClass) {
                $requestAttributes['flare.entry_point.class'] = $entryPointClass;
            }

            return [
                'name' => "Request - {$requestAttributes['url.full']}",
                'attributes' => [
                    'flare.span_type' => SpanType::Request,
                    ...$requestAttributes,
                    ...$attributes,
                ],
            ];
        });
    }

    public function recordEnd(
        ?int $responseStatusCode = null,
        ?int $responseBodySize = null,
        array $attributes = [],
    ): ?Span {
        if ($responseStatusCode) {
            $attributes['http.response.status_code'] = $responseStatusCode;
        }

        if ($responseBodySize) {
            $attributes['http.response.body.size'] = $responseBodySize;
        }

        return $this->endSpan(additionalAttributes: $attributes);
    }
}

