<?php

namespace Spatie\FlareClient\Disabled;

use Closure;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Recorders\ApplicationRecorder\ApplicationRecorder;
use Spatie\FlareClient\Recorders\CacheRecorder\CacheRecorder;
use Spatie\FlareClient\Recorders\CommandRecorder\CommandRecorder;
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
use Spatie\FlareClient\Report;
use Spatie\FlareClient\Support\SentReports;
use Spatie\FlareClient\Tracer;
use Throwable;

class DisabledFlare extends Flare
{
    public SentReports $disabledSentReports;

    public ApplicationRecorder $disabledApplicationRecorder;

    public function __construct(
        public readonly Tracer $tracer = new DisabledTracer()
    ) {
    }

    public static function make(
        string|FlareConfig $apiToken,
    ): self {
        return new self;
    }

    public function registerFlareHandlers(): self
    {
        return $this;
    }

    public function registerExceptionHandler(): self
    {
        return $this;
    }

    public function registerErrorHandler(?int $errorLevels = null): self
    {
        return $this;
    }

    public function bootRecorders(): self
    {
        return $this;
    }

    public function application(): ApplicationRecorder
    {
        return $this->disabledApplicationRecorder ??= new DisabledApplicationRecorder();
    }

    public function cache(): CacheRecorder|null
    {
        return null;
    }

    public function command(): CommandRecorder|null
    {
        return null;
    }

    public function externalHttp(): ExternalHttpRecorder|null
    {
        return null;
    }

    public function filesystem(): FilesystemRecorder|null
    {
        return null;
    }

    public function glow(): GlowRecorder|null
    {
        return null;
    }

    public function log(): LogRecorder|null
    {
        return null;
    }

    public function query(): QueryRecorder|null
    {
        return null;
    }

    public function redisCommand(): RedisCommandRecorder|null
    {
        return null;
    }

    public function request(): RequestRecorder|null
    {
        return null;
    }

    public function response(): ResponseRecorder|null
    {
        return null;
    }

    public function routing(): RoutingRecorder|null
    {
        return null;
    }

    public function transaction(): TransactionRecorder|null
    {
        return null;
    }

    public function view(): ViewRecorder|null
    {
        return null;
    }

    public function handleException(Throwable $throwable): void
    {
    }

    public function handleError(mixed $code, string $message, string $file = '', int $line = 0): void
    {

    }

    public function report(
        Throwable $throwable,
        ?callable $callback = null,
        ?bool $handled = null
    ): ?Report {
        return $this->emptyReport();
    }

    public function createReport(
        Throwable $throwable,
        ?callable $callback = null,
        ?bool $handled = null
    ): Report {
        return $this->emptyReport();
    }

    public function reportHandled(Throwable $throwable): ?Report
    {
        return $this->emptyReport();
    }

    public function reportMessage(
        string $message,
        string $logLevel,
        ?callable $callback = null,
    ): Report {
        return $this->emptyReport();
    }

    public function sendTestReport(Throwable $throwable): void
    {
    }

    public function withApplicationVersion(string|Closure $version): self
    {
        return $this;
    }

    public function withApplicationName(string|Closure $name): self
    {
        return $this;
    }

    public function withApplicationStage(string|Closure $stage): self
    {
        return $this;
    }

    public function withSolutionProvider(string ...$solutionProviders): self
    {
        return $this;
    }

    public function filterExceptionsUsing(Closure $filterExceptionsCallable): static
    {
        return $this;
    }

    public function filterReportsUsing(Closure $filterReportsCallable): static
    {
        return $this;
    }

    public function sendReportsImmediately(bool $sendReportsImmediately = true): self
    {
        return $this;
    }

    public function reset(
        bool $reports = true,
        bool $traces = true,
    ): void {

    }

    public function sentReports(): SentReports
    {
        return $this->disabledSentReports ??= new SentReports();
    }

    protected function emptyReport(): Report
    {
        return new Report([], 'Flare is not enabled', 'Flare is not enabled', false, 0);
    }
}
