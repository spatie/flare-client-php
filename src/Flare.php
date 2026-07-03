<?php

namespace Spatie\FlareClient;

use Closure;
use Exception;
use Spatie\FlareClient\Contracts\Recorders\Recorder;
use Spatie\FlareClient\Enums\FlareMode;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Recorders\CacheRecorder\CacheRecorder;
use Spatie\FlareClient\Recorders\CommandRecorder\CommandRecorder;
use Spatie\FlareClient\Recorders\ContextRecorder\ContextRecorder;
use Spatie\FlareClient\Recorders\ExternalHttpRecorder\ExternalHttpRecorder;
use Spatie\FlareClient\Recorders\FilesystemRecorder\FilesystemRecorder;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Spatie\FlareClient\Recorders\JobRecorder\JobRecorder;
use Spatie\FlareClient\Recorders\QueryRecorder\QueryRecorder;
use Spatie\FlareClient\Recorders\QueueRecorder\QueueRecorder;
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
    public function __construct(
        public readonly Lifecycle $lifecycle,
        public readonly Tracer $tracer,
        public readonly Logger $logger,
        public readonly Reporter $reporter,
        public readonly BackTracer $backTracer,
        public readonly Ids $ids,
        public readonly Time $time,
        public readonly SentReports $sentReports,
        public readonly FlareMode $mode,
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

    public function start(
        ?int $timeUnixNano = null,
        array $attributes = [],
        ?string $traceparent = null,
    ): self {
        if ($this->lifecycle->usesSubtasks) {
            throw new Exception(
                'Flare::start() is for the main lifecycle. In subtask mode, use $flare->lifecycle->startSubtask() per work unit.'
            );
        }

        $this->reporter->registerFlareHandlers();
        $this->lifecycle->start($timeUnixNano, $attributes, $traceparent);

        return $this;
    }

    public function end(
        ?int $timeUnixNano = null,
        array $additionalTerminationAttributes = [],
        array $additionalApplicationAttributes = [],
    ): void {
        if ($this->lifecycle->usesSubtasks) {
            throw new Exception(
                'Flare::end() is for the main lifecycle. In subtask mode, use $flare->lifecycle->endSubtask() per work unit.'
            );
        }

        $this->lifecycle->terminated(
            $timeUnixNano,
            $additionalTerminationAttributes,
            $additionalApplicationAttributes,
        );
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

    /**
     * @param Closure(ReportFactory): void $renderReportCallable
     */
    public function renderReportsUsing(Closure $renderReportCallable): static
    {
        $this->reporter->renderReportsUsing($renderReportCallable);

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

    public function job(): JobRecorder|null
    {
        return $this->recorders->getRecorder(RecorderType::Job->value);
    }

    public function queue(): QueueRecorder|null
    {
        return $this->recorders->getRecorder(RecorderType::Queue->value);
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
        $this->resource->serviceName($name instanceof Closure ? $name() : $name);

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
