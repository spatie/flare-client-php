<?php

namespace Spatie\FlareClient\Support;

use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\Backtrace\Arguments\Reducers\ArgumentReducer;
use Spatie\ErrorSolutions\Contracts\HasSolutionsForThrowable;
use Spatie\FlareClient\Contracts\FlareCollectType;
use Spatie\FlareClient\Contracts\Recorders\Recorder;
use Spatie\FlareClient\Enums\CollectType;
use Spatie\FlareClient\FlareMiddleware\AddConsoleInformation;
use Spatie\FlareClient\FlareMiddleware\AddGitInformation;
use Spatie\FlareClient\FlareMiddleware\AddRequestInformation;
use Spatie\FlareClient\FlareMiddleware\AddSolutions;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\Recorders\ApplicationRecorder\ApplicationRecorder;
use Spatie\FlareClient\Recorders\CacheRecorder\CacheRecorder;
use Spatie\FlareClient\Recorders\CommandRecorder\CommandRecorder;
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
use Spatie\FlareClient\Resources\Resource;

class CollectsResolver
{
    /** @var array<class-string<FlareMiddleware>, array<string, mixed>> */
    protected array $middlewares = [];

    /** @var array<class-string<Recorder>, array<string, mixed>> */
    protected array $recorders = [];

    /** @var array<class-string<HasSolutionsForThrowable>> */
    protected array $solutionProviders = [];

    /** @var array<callable(Resource):Resource> */
    protected array $resourceModifiers = [];

    /** @var array<ArgumentReducer|class-string<ArgumentReducer>>|ArgumentReducers */
    private array|ArgumentReducers $argumentReducers = [];

    private bool $collectStackFrameArguments = false;

    private bool $forcePHPStackFrameArgumentsIniSetting = false;

    protected bool $collectErrorsWithTraces = false;

    /** @return array{middlewares: array<class-string<FlareMiddleware>, array>, recorders: array<class-string<Recorder>, array>, solutionProviders: array<class-string<HasSolutionsForThrowable>>, resourceModifiers: array<callable(Resource):Resource>, collectStackFrameArguments: bool, argumentReducers: array<class-string<ArgumentReducer>|ArgumentReducer>|ArgumentReducers, forcePHPStackFrameArgumentsIniSetting: bool, collectErrorsWithTraces:bool} */
    public function execute(
        array $collects,
    ): array {
        $this->middlewares = [];
        $this->recorders = [];
        $this->resourceModifiers = [];

        $this->application(); // Always enabled

        foreach ($collects as $collect) {
            $ignored = $collect['ignored'] ?? false;

            if ($ignored) {
                continue;
            }

            $options = $collect['options'] ?? [];

            match ($collect['type'] ?? null) {
                CollectType::Requests => $this->requests($options),
                CollectType::Commands => $this->console($options),
                CollectType::GitInfo => $this->gitInfo($options),
                CollectType::Cache => $this->cache($options),
                CollectType::Glows => $this->glows($options),
                CollectType::Logs => $this->logs($options),
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

        return [
            'middlewares' => $this->middlewares,
            'recorders' => $this->recorders,
            'solutionProviders' => $this->solutionProviders,
            'collectStackFrameArguments' => $this->collectStackFrameArguments,
            'argumentReducers' => $this->argumentReducers,
            'forcePHPStackFrameArgumentsIniSetting' => $this->forcePHPStackFrameArgumentsIniSetting,
            'resourceModifiers' => $this->resourceModifiers,
            'collectErrorsWithTraces' => $this->collectErrorsWithTraces,
        ];
    }

    protected function handleUnknownCollectType(
        FlareCollectType $type,
        array $options
    ): void {

    }

    protected function application(): void
    {
        $this->addRecorder(ApplicationRecorder::class);
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

    protected function gitInfo(array $options): void
    {
        $this->resourceModifiers[] = fn (Resource $resource) => $resource->git();
        $this->addMiddleware(AddGitInformation::class);
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

    protected function logs(array $options): void
    {
        $this->addRecorder($options['recorder'] ?? LogRecorder::class, $this->only($options, [
            'with_traces',
            'with_errors',
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
            'track_all_disks'
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
        if ($options['host'] ?? false) {
            $this->resourceModifiers[] = fn (Resource $resource) => $resource->host();
        }

        if ($options['php'] ?? false) {
            $this->resourceModifiers[] = fn (Resource $resource) => $resource->process()->processRuntime();
        }

        if ($options['os'] ?? false) {
            $this->resourceModifiers[] = fn (Resource $resource) => $resource->operatingSystem();
        }

        if ($options['composer'] ?? false) {
            $this->resourceModifiers[] = fn (Resource $resource) => $resource->composer();
        }
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
}
