<?php

namespace Spatie\FlareClient\Support;

use Monolog\Level;
use Psr\Container\ContainerInterface;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\Backtrace\Arguments\Reducers\ArgumentReducer;
use Spatie\ErrorSolutions\Contracts\HasSolutionsForThrowable;
use Spatie\FlareClient\AttributesProviders\GitAttributesProvider;
use Spatie\FlareClient\Contracts\FlareCollectType;
use Spatie\FlareClient\Contracts\Recorders\Recorder;
use Spatie\FlareClient\Enums\CollectType;
use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\FlareMiddleware\AddConsoleInformation;
use Spatie\FlareClient\FlareMiddleware\AddLogs;
use Spatie\FlareClient\FlareMiddleware\AddRequestInformation;
use Spatie\FlareClient\FlareMiddleware\AddSolutions;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\Logger;
use Spatie\FlareClient\Recorders\CacheRecorder\CacheRecorder;
use Spatie\FlareClient\Recorders\CommandRecorder\CommandRecorder;
use Spatie\FlareClient\Recorders\ContextRecorder\ContextRecorder;
use Spatie\FlareClient\Recorders\DumpRecorder\DumpRecorder;
use Spatie\FlareClient\Recorders\ExternalHttpRecorder\ExternalHttpRecorder;
use Spatie\FlareClient\Recorders\FilesystemRecorder\FilesystemRecorder;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Spatie\FlareClient\Recorders\LogRecorder\LogRecorder;
use Spatie\FlareClient\Recorders\QueryRecorder\QueryRecorder;
use Spatie\FlareClient\Recorders\RedisCommandRecorder\RedisCommandRecorder;
use Spatie\FlareClient\Recorders\RequestRecorder\RequestRecorder;
use Spatie\FlareClient\Recorders\ResponseRecorder\ResponseRecorder;
use Spatie\FlareClient\Recorders\RoutingRecorder\RoutingRecorder;
use Spatie\FlareClient\Recorders\TransactionRecorder\TransactionRecorder;
use Spatie\FlareClient\Recorders\ViewRecorder\ViewRecorder;
use Spatie\FlareClient\Reporter;
use Spatie\FlareClient\Resources\Resource;

class CollectsResolver
{
    /** @var array<class-string<FlareMiddleware>, array<string, mixed>> */
    public array $middlewares = [];

    /** @var array<class-string<Recorder>, array<string, mixed>> */
    public array $recorders = [];

    /** @var array<class-string<HasSolutionsForThrowable>> */
    public array $solutionProviders = [];

    /** @var array<callable(Resource, ContainerInterface):Resource> */
    public array $resourceModifiers = [];

    /** @var array<ArgumentReducer|class-string<ArgumentReducer>>|ArgumentReducers */
    public array|ArgumentReducers $argumentReducers = [];

    public bool $collectStackFrameArguments = false;

    public bool $forcePHPStackFrameArgumentsIniSetting = false;

    public bool $collectErrorsWithTraces = false;

    public function execute(
        array $collects,
    ): self {
        $this->middlewares = [];
        $this->recorders = [];
        $this->resourceModifiers = [];

        foreach ($collects as $collect) {
            $ignored = $collect['ignored'] ?? false;

            if ($ignored) {
                continue;
            }

            $options = $collect['options'] ?? [];

            match ($collect['type'] ?? null) {
                CollectType::Requests => $this->requests($options),
                CollectType::Commands => $this->console($options),
                CollectType::Context => $this->context($collect),
                CollectType::GitInfo => $this->gitInfo($options),
                CollectType::Cache => $this->cache($options),
                CollectType::Glows => $this->glows($options),
                CollectType::LogsWithErrors => $this->logsWithErrors($options),
                CollectType::Solutions => $this->solutions($options),
                CollectType::Dumps => $this->dumps($options),
                CollectType::Queries => $this->queries($options),
                CollectType::Transactions => $this->transactions($options),
                CollectType::ExternalHttp => $this->externalHttp($options),
                CollectType::Filesystem => $this->filesystem($options),
                CollectType::RedisCommands => $this->redisCommands($options),
                CollectType::Views => $this->views($options),
                CollectType::ServerInfo => $this->severInfo($options),
                CollectType::StackFrameArguments => $this->stackFrameArguments($options),
                CollectType::Recorders => $this->recorders($options),
                CollectType::FlareMiddleware => $this->flareMiddleware($options),
                CollectType::ErrorsWithTraces => $this->errorsWithTraces($options),
                CollectType::Application, null => null,
                default => $this->handleUnknownCollectType($collect['type'], $options),
            };
        }

        return $this;
    }

    protected function handleUnknownCollectType(
        FlareCollectType $type,
        array $options
    ): void {

    }

    protected function errorsWithTraces(array $options): void
    {
        $this->collectErrorsWithTraces = $options['with_traces'] ?? false;
    }

    protected function requests(
        array $options
    ): void {
        $this->addMiddleware($options['middleware'] ?? AddRequestInformation::class);
        $this->addRecorder(RequestRecorder::class);
        $this->addRecorder(RoutingRecorder::class);
        $this->addRecorder(ResponseRecorder::class);
    }

    protected function console(
        array $options
    ): void {
        $this->addMiddleware($options['middleware'] ?? AddConsoleInformation::class);
        $this->addRecorder(
            $options['recorder'] ?? CommandRecorder::class,
            $this->only($options, [
                'with_traces',
                'with_errors',
                'max_items_with_errors',
            ])
        );
    }

    protected function context(
        array $options
    ): void {
        $this->addRecorder(
            ContextRecorder::class,
            $options,
        );
    }

    protected function gitInfo(array $options): void
    {
        $this->resourceModifiers[] = fn (Resource $resource, ContainerInterface $container) => $resource->git(
            attributesProvider: $container->get(GitAttributesProvider::class),
            useProcess: $options['use_process'] ?? Resource::DEFAULT_GIT_USE_PROCESS,
            entityTypes: $this->resolveChosenFlareEntityTypes($options['entity_types'] ?? Resource::DEFAULT_GIT_ENTITY_TYPES),
        );
    }

    protected function cache(array $options): void
    {
        $this->addRecorder($options['recorder'] ?? CacheRecorder::class, $this->only($options, [
            'with_traces',
            'with_errors',
            'max_items_with_errors',
            'operations',
        ]));
    }

    protected function glows(array $options): void
    {
        $this->addRecorder($options['recorder'] ?? GlowRecorder::class, $this->only($options, [
            'with_traces',
            'with_errors',
            'max_items_with_errors',
        ]));
    }

    protected function logsWithErrors(array $options): void
    {
        $this->addMiddleware($options['middleware'] ?? AddLogs::class, $this->only($options, [
            'max_items_with_errors',
            'minimal_level',
        ]));
    }

    protected function solutions(array $solutions): void
    {
        $this->addMiddleware($solutions['middleware'] ?? AddSolutions::class);
        $this->solutionProviders = $solutions['solution_providers'] ?? [];
    }

    protected function dumps(array $options): void
    {
        $this->addRecorder(
            $options['recorder'] ?? DumpRecorder::class,
            $this->only($options, [
                'with_traces',
                'with_errors',
                'max_items_with_errors',
                'find_dump',
            ])
        );
    }

    protected function queries(array $options): void
    {
        $this->addRecorder($options['recorder'] ?? QueryRecorder::class, $this->only($options, [
            'with_traces',
            'with_errors',
            'max_items_with_errors',
            'include_bindings',
            'find_origin',
            'find_origin_threshold',
        ]));
    }

    protected function transactions(array $options): void
    {
        $this->addRecorder($options['recorder'] ?? TransactionRecorder::class, $this->only($options, [
            'with_traces',
            'with_errors',
            'max_items_with_errors',
        ]));
    }

    protected function externalHttp(array $options): void
    {
        $this->addRecorder($options['recorder'] ?? ExternalHttpRecorder::class, $this->only($options, [
            'with_traces',
            'with_errors',
            'max_items_with_errors',
        ]));
    }

    protected function filesystem(array $options): void
    {
        $this->addRecorder($options['recorder'] ?? FilesystemRecorder::class, $this->only($options, [
            'with_traces',
            'with_errors',
            'max_items_with_errors',
            'track_all_disks',
        ]));
    }

    protected function redisCommands(array $options): void
    {
        $this->addRecorder($options['recorder'] ?? RedisCommandRecorder::class, $this->only($options, [
            'with_traces',
            'with_errors',
            'max_items_with_errors',
        ]));
    }

    protected function views(array $options): void
    {
        $this->addRecorder($options['recorder'] ?? ViewRecorder::class, $this->only($options, [
            'with_traces',
            'with_errors',
        ]));
    }

    protected function severInfo(array $options): void
    {
        $this->resourceModifiers[] = fn (Resource $resource) => $resource->host(
            entityTypes: $this->resolveChosenFlareEntityTypes($options['host'] ?? Resource::DEFAULT_HOST_ENTITY_TYPES),
        );

        $this->resourceModifiers[] = fn (Resource $resource) => $resource
            ->process(entityTypes: $this->resolveChosenFlareEntityTypes($options['php'] ?? Resource::DEFAULT_PHP_ENTITY_TYPES))
            ->processRuntime(entityTypes: $this->resolveChosenFlareEntityTypes($options['php'] ?? Resource::DEFAULT_PHP_ENTITY_TYPES));

        $this->resourceModifiers[] = fn (Resource $resource) => $resource->operatingSystem(
            entityTypes: $this->resolveChosenFlareEntityTypes($options['os'] ?? Resource::DEFAULT_OS_ENTITY_TYPES),
        );

        $this->resourceModifiers[] = fn (Resource $resource) => $resource->composerPackages(
            entityTypes: $this->resolveChosenFlareEntityTypes($options['composer_packages'] ?? Resource::DEFAULT_COMPOSER_PACKAGES_ENTITY_TYPES)
        );
    }

    protected function recorders(array $options): void
    {
        foreach ($options['recorders'] ?? [] as $recorderClass => $recorderOptions) {
            $this->addRecorder($recorderClass, $recorderOptions);
        }
    }

    protected function flareMiddleware(array $options): void
    {
        foreach ($options['flare_middleware'] ?? [] as $middlewareClass => $middlewareOptions) {
            $this->addMiddleware($middlewareClass, $middlewareOptions);
        }
    }

    protected function stackFrameArguments(array $options): void
    {
        $this->collectStackFrameArguments = true;
        $this->argumentReducers = $options['argument_reducers'] ?? [];
        $this->forcePHPStackFrameArgumentsIniSetting = $options['force_php_ini_setting'] ?? false;
    }

    /**
     * @param class-string<FlareMiddleware>|null $middleware
     */
    protected function addMiddleware(
        ?string $middleware,
        array $options = []
    ): void {
        if (! $middleware) {
            return;
        }

        $this->middlewares[$middleware] = $options;
    }

    /**
     * @param class-string<Recorder>|null $recorder
     */
    protected function addRecorder(
        ?string $recorder,
        array $options = []
    ): void {
        if (! $recorder) {
            return;
        }

        $this->recorders[$recorder] = $options;
    }

    protected function only(
        array $options,
        array|string $keys
    ): array {
        return array_intersect_key($options, array_flip((array) $keys));
    }

    /**
     * @param array<int, FlareEntityType>|bool|FlareEntityType $entityTypes
     */
    protected function resolveChosenFlareEntityTypes(
        array|bool|FlareEntityType $entityTypes
    ): array {
        if ($entityTypes === true) {
            return FlareEntityType::cases();
        }

        if ($entityTypes === false) {
            return [];
        }

        if (is_array($entityTypes)) {
            return $entityTypes;
        }

        return [$entityTypes];
    }
}
