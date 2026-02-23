<?php

namespace Spatie\FlareClient;

use Closure;
use Exception;
use Spatie\ErrorSolutions\Contracts\HasSolutionsForThrowable;
use Spatie\FlareClient\Contracts\Recorders\Recorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Recorders\CacheRecorder\CacheRecorder;
use Spatie\FlareClient\Recorders\CommandRecorder\CommandRecorder;
use Spatie\FlareClient\Recorders\ContextRecorder\ContextRecorder;
use Spatie\FlareClient\Recorders\ExternalHttpRecorder\ExternalHttpRecorder;
use Spatie\FlareClient\Recorders\FilesystemRecorder\FilesystemRecorder;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Spatie\FlareClient\Recorders\QueryRecorder\QueryRecorder;
use Spatie\FlareClient\Recorders\RedisCommandRecorder\RedisCommandRecorder;
use Spatie\FlareClient\Recorders\RequestRecorder\RequestRecorder;
use Spatie\FlareClient\Recorders\ResponseRecorder\ResponseRecorder;
use Spatie\FlareClient\Recorders\RoutingRecorder\RoutingRecorder;
use Spatie\FlareClient\Recorders\TransactionRecorder\TransactionRecorder;
use Spatie\FlareClient\Recorders\ViewRecorder\ViewRecorder;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\Container;
use Spatie\FlareClient\Support\Ids;
use Spatie\FlareClient\Support\Lifecycle;
use Spatie\FlareClient\Support\Recorders;
use Spatie\FlareClient\Support\SentReports;
use Spatie\FlareClient\Time\Time;
use Throwable;

class Flare
{
    // TODO: agent
    // TODO: check current GH PR's and issues if we need to make changes
    // TODO: quick tests on Vapor
    // TODO: add ability to ignore certain commands and requests like we do with jobs
    // TODO: dynamic sampling based upon context would be cool
    // TODO: wp-admin.php calls
    // TODO (less important): write a framework integration guide

    public function __construct(
        public readonly Lifecycle $lifecycle,
        public readonly Tracer $tracer,
        public readonly Logger $logger,
        public readonly Reporter $reporter,
        public readonly BackTracer $backTracer,
        public readonly Ids $ids,
        public readonly Time $time,
        public readonly SentReports $sentReports,
        protected Resource $resource,
        protected Scope $scope,
        protected Recorders $recorders,
    ) {
    }

    public static function make(
        string|FlareConfig $apiToken,
    ): self {
        $config = is_string($apiToken) ? FlareConfig::make($apiToken)->useDefaults() : $apiToken;

        $container = Container::instance();

        $provider = new FlareProvider($config, $container);

        $provider->register();
        $provider->boot();

        return $container->get(Flare::class);
    }

    public function registerFlareHandlers(): self
    {
        $this->reporter->registerFlareHandlers();

        return $this;
    }

    public function registerExceptionHandler(): self
    {
        $this->reporter->registerExceptionHandler();

        return $this;
    }

    public function registerErrorHandler(?int $errorLevels = null): self
    {
        $this->reporter->registerErrorHandler($errorLevels);

        return $this;
    }

    /**
     * @param Closure(ReportFactory $report):void|null $callback
     */
    public function report(
        Throwable $throwable,
        ?Closure $callback = null,
        ?bool $handled = null
    ): ?ReportFactory {
        return $this->reporter->report($throwable, $callback, $handled);
    }

    public function createReport(
        Throwable $throwable,
        ?Closure $callback = null,
        ?bool $handled = null
    ): ReportFactory {
        return $this->reporter->createReport($throwable, $callback, $handled);
    }

    public function reportHandled(Throwable $throwable): ?ReportFactory
    {
        return $this->reporter->reportHandled($throwable);
    }

    /**
     * @param class-string<HasSolutionsForThrowable> ...$solutionProviders
     */
    public function withSolutionProvider(string ...$solutionProviders): self
    {
        $this->reporter->withSolutionProvider(...$solutionProviders);

        return $this;
    }

    /**
     * @param Closure(Exception): bool $filterExceptionsCallable
     */
    public function filterExceptionsUsing(Closure $filterExceptionsCallable): static
    {
        $this->reporter->filterExceptionsUsing($filterExceptionsCallable);

        return $this;
    }

    /**
     * @param Closure(ReportFactory): bool $filterReportsCallable
     */
    public function filterReportsUsing(Closure $filterReportsCallable): static
    {
        $this->reporter->filterReportsUsing($filterReportsCallable);

        return $this;
    }

    public function cache(): CacheRecorder|null
    {
        return $this->recorders->getRecorder(RecorderType::Cache->value);
    }

    public function command(): CommandRecorder|null
    {
        return $this->recorders->getRecorder(RecorderType::Command->value);
    }

    public function externalHttp(): ExternalHttpRecorder|null
    {
        return $this->recorders->getRecorder(RecorderType::ExternalHttp->value);
    }

    public function filesystem(): FilesystemRecorder|null
    {
        return $this->recorders->getRecorder(RecorderType::Filesystem->value);
    }

    public function glow(): GlowRecorder|null
    {
        return $this->recorders->getRecorder(RecorderType::Glow->value);
    }

    public function sentReports(): SentReports
    {
        return $this->sentReports;
    }

    public function log(): Logger
    {
        return $this->logger;
    }

    public function query(): QueryRecorder|null
    {
        return $this->recorders->getRecorder(RecorderType::Query->value);
    }

    public function redisCommand(): RedisCommandRecorder|null
    {
        return $this->recorders->getRecorder(RecorderType::RedisCommand->value);
    }

    public function request(): RequestRecorder|null
    {
        return $this->recorders->getRecorder(RecorderType::Request->value);
    }

    public function response(): ResponseRecorder|null
    {
        return $this->recorders->getRecorder(RecorderType::Response->value);
    }

    public function routing(): RoutingRecorder|null
    {
        return $this->recorders->getRecorder(RecorderType::Routing->value);
    }

    public function transaction(): TransactionRecorder|null
    {
        return $this->recorders->getRecorder(RecorderType::Transaction->value);
    }

    public function view(): ViewRecorder|null
    {
        return $this->recorders->getRecorder(RecorderType::View->value);
    }

    public function recorder(
        RecorderType|string $type
    ): Recorder|null {
        return $this->recorders->getRecorder($type);
    }

    public function withApplicationVersion(string|Closure $version): self
    {
        $this->resource->serviceVersion(is_callable($version) ? $version() : $version);

        return $this;
    }

    public function withApplicationName(string|Closure $name): self
    {
        $this->resource->serviceName(is_callable($name) ? $name() : $name);

        return $this;
    }

    public function withApplicationStage(string|Closure $stage): self
    {
        $this->resource->serviceStage(is_callable($stage) ? $stage() : $stage);

        return $this;
    }

    public function context(string|array $key, mixed $value = null): self
    {
        /** @var ?ContextRecorder $contextRecorder */
        $contextRecorder = $this->recorders->getRecorder(RecorderType::Context);

        $contextRecorder?->record('context.custom', $key, $value);

        return $this;
    }
}
