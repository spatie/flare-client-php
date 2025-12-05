<?php

namespace Spatie\FlareClient\Support;

use Closure;
use Exception;
use Psr\Container\ContainerInterface;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use Spatie\FlareClient\Support\Exceptions\ContainerEntryNotFoundException;

/**
 * @phpstan-type AutowiringDefinition array<string, array{id: class-string, default?: mixed}>
 */
class Container implements ContainerInterface
{
    private static self $instance;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * @template T
     *
     * @param array<class-string<T>, Closure(): T> $definitions
     * @param array<class-string<T>, Closure(): T> $singletons
     * @param array<class-string<T>, T> $initializedSingletons
     * @param array<class-string<T>, AutowiringDefinition> $autoWiringDefinitions
     */
    protected function __construct(
        protected array $definitions = [],
        protected array $singletons = [],
        protected array $initializedSingletons = [],
        protected array $autoWiringDefinitions = []
    ) {
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     * @param null|Closure():T|class-string<T> $builder
     */
    public function singleton(string $class, null|string|Closure $builder = null): void
    {
        $this->singletons[$class] = match (true) {
            $builder === null => fn () => new $class(),
            is_string($builder) => fn () => new $builder(),
            default => $builder,
        };
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     * @param null|Closure():T $builder
     */
    public function bind(string $class, ?Closure $builder = null): void
    {
        $this->definitions[$class] = $builder ?? fn () => new $class();
    }

    /**
     * @template T
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    public function get(string $id)
    {
        return $this->resolve($id, []) ?? throw ContainerEntryNotFoundException::make($id);
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->definitions) || array_key_exists($id, $this->singletons);
    }

    /**
     * @template T
     *
     * @param class-string<T> $id
     * @param array<string, mixed> $parameters
     *
     * @return ?T
     */
    public function resolve(string $id, array $parameters): mixed
    {
        if (array_key_exists($id, $this->initializedSingletons)) {
            return $this->initializedSingletons[$id];
        }

        if (array_key_exists($id, $this->singletons)) {
            return $this->initializedSingletons[$id] = $this->singletons[$id]();
        }

        if (array_key_exists($id, $this->definitions)) {
            return $this->definitions[$id]();
        }

        return $this->tryAutowiringDefinition($id, $parameters) ?? null;
    }

    public function reset(): void
    {
        $this->definitions = [];
        $this->singletons = [];
        $this->initializedSingletons = [];
        $this->autoWiringDefinitions = [];
    }

    /**
     * @template T
     *
     * @param class-string<T> $id
     * @param array<string, mixed> $providedParameters
     *
     * @return T|null
     */
    protected function tryAutowiringDefinition(
        string $id,
        array $providedParameters = []
    ): mixed {
        $definition = $this->resolveAutowiringDefinition($id);

        if ($definition === null) {
            return null;
        }

        $parameters = [];
        $missingParameters = [];

        foreach ($definition as $parameter => $parameterDefinition) {
            if (array_key_exists($parameter, $providedParameters)) {
                $parameters[$parameter] = $providedParameters[$parameter];

                continue;
            }

            $resolvedParameter = $this->resolve($parameterDefinition['id'], []);

            if ($resolvedParameter !== null) {
                $parameters[$parameter] = $resolvedParameter;

                continue;
            }

            if (array_key_exists('default', $parameterDefinition)) {
                $parameters[$parameter] = $parameterDefinition['default'];

                continue;
            }

            $missingParameters[] = $parameter;
        }

        if (count($missingParameters) > 0) {
            throw new Exception("Cannot autowire {$id}. Missing parameters: ".implode(', ', $missingParameters));
        }

        return new $id(...$parameters);
    }

    /**
     * @template T
     *
     * @param class-string<T> $id
     *
     * @return ?AutowiringDefinition
     */
    protected function resolveAutowiringDefinition(string $id): ?array
    {
        if (array_key_exists($id, $this->autoWiringDefinitions)) {
            return $this->autoWiringDefinitions[$id];
        }

        if (! class_exists($id)) {
            return null;
        }

        if (enum_exists($id)) {
            return null;
        }

        $definition = [];

        if (! method_exists($id, '__construct')) {
            return $definition;
        }

        try {
            $constructor = new ReflectionMethod($id, '__construct');

            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();

                if (! $type instanceof ReflectionNamedType) {
                    throw new Exception("Cannot autowire {$id} constructor parameter {$parameter->getName()}");
                }

                $definition[$parameter->getName()] = [
                    'id' => $type->getName(),
                    'default' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
                ];
            }
        } catch (ReflectionException $exception) {
            return null;
        }

        return $definition;
    }
}
