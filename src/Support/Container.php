<?php

namespace Spatie\FlareClient\Support;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Support\Exceptions\ContainerEntryNotFoundException;

class Container implements ContainerInterface
{
    private static self $instance;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * @param array<class-string, Closure(): object> $definitions
     * @param array<class-string, Closure(): object> $singletons
     * @param array<class-string, object> $initializedSingletons
     */
    protected function __construct(
        protected array $definitions = [],
        protected array $singletons = [],
        protected array $initializedSingletons = [],
    ) {
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     * @param Closure():T $builder
     */
    public function singleton(string $class, ?Closure $builder = null): void
    {
        $this->singletons[$class] = $builder ?? fn () => new $class();
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     * @param Closure():T $builder
     */
    public function bind(string $class, Closure $builder): void
    {
        $this->definitions[$class] = $builder;
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
        if (array_key_exists($id, $this->initializedSingletons)) {
            return $this->initializedSingletons[$id];
        }

        if (array_key_exists($id, $this->singletons)) {
            return $this->initializedSingletons[$id] = $this->singletons[$id]();
        }

        if (array_key_exists($id, $this->definitions)) {
            return $this->definitions[$id]();
        }

        throw ContainerEntryNotFoundException::make($id);
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->definitions) || array_key_exists($id, $this->singletons);
    }

    public function reset(): void
    {
        $this->definitions = [];
        $this->singletons = [];
        $this->initializedSingletons = [];
    }
}
