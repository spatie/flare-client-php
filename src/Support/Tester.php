<?php

namespace Spatie\FlareClient\Support;

use Closure;
use Exception;
use Monolog\Level;
use Spatie\FlareClient\Api;
use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Logger;
use Spatie\FlareClient\Memory\Memory;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Sampling\AlwaysSampler;
use Spatie\FlareClient\Senders\DaemonSender;
use Spatie\FlareClient\Senders\Exceptions\BadResponseCode;
use Spatie\FlareClient\Senders\Exceptions\DaemonTimeoutException;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Time\Time;
use Spatie\FlareClient\Tracer;
use Throwable;

class Tester
{
    /** @var Closure(string): void */
    protected Closure $onInfo;

    /** @var Closure(string): void */
    protected Closure $onWarning;

    /** @var Closure(string): void */
    protected Closure $onError;

    /**
     * @param Closure(string): void|null $onInfo
     * @param Closure(string): void|null $onWarning
     * @param Closure(string): void|null $onError
     */
    public function __construct(
        protected Api $api,
        protected Ids $ids,
        protected Time $time,
        protected Memory $memory,
        protected Resource $resource,
        protected ReportFactory $reportFactory,
        protected Sender $sender,
        ?Closure $onInfo = null,
        ?Closure $onWarning = null,
        ?Closure $onError = null,
    ) {
        $this->onInfo = $onInfo ?? function (string $message): void {
        };
        $this->onWarning = $onWarning ?? function (string $message): void {
        };
        $this->onError = $onError ?? function (string $message): void {
        };
    }

    /**
     * @param array<int, FlareEntityType> $types
     */
    public function test(array $types, int $daemonTimeout = 30): bool
    {
        $allPassed = true;

        foreach (FlareEntityType::cases() as $type) {
            if (! in_array($type, $types, true)) {
                ($this->onWarning)("Skipping {$type->singleName()} test (disabled)");

                continue;
            }

            $passed = $this->sender instanceof DaemonSender
                ? $this->testViaDaemon($type, $daemonTimeout)
                : $this->testViaCurl($type);

            if (! $passed) {
                $allPassed = false;
            }
        }

        return $allPassed;
    }

    public function reportSupportInfo(): void
    {
        ($this->onInfo)('Need help? Visit https://flareapp.io/docs or contact support@flareapp.io');
    }

    /** @return array<string, string> */
    public function platformInfo(): array
    {
        $curlVersion = function_exists('curl_version') ? (curl_version() ?: []) : [];

        return [
            'Platform' => 'PHP',
            'PHP' => PHP_VERSION,
            'SDK' => Telemetry::getVersion(),
            'Curl' => $curlVersion['version'] ?? 'N/A',
            'SSL' => $curlVersion['ssl_version'] ?? 'N/A',
        ];
    }

    public function buildThrowable(): Throwable
    {
        return new Exception('This is an exception to test if the integration with Flare works.');
    }

    public function report(?Throwable $throwable = null): void
    {
        $this->api->report(
            $this->reportFactory->new()->throwable($throwable ?? $this->buildThrowable()),
            test: true,
        );
    }

    /** @return array<int, Span> */
    public function buildTestTrace(): array
    {
        $tracer = $this->getTestTracer();
        $time = $this->time->getCurrentTime();

        $tracer->startTrace();

        $tracer->startSpan(
            name: "App - {$this->resource->serviceName}",
            time: $time,
            attributes: ['flare.span_type' => SpanType::Application,]
        );

        $tracer->span(
            name: "Registering App",
            callback: function () {
            },
            attributes: ['flare.span_type' => SpanType::ApplicationRegistration],
            startTime: $time + rand(20_000_000, 60_000_000),
            endTime: $time + rand(60_000_000, 140_000_000),
        );

        $tracer->span(
            name: "Booting App",
            callback: function () {
            },
            attributes: ['flare.span_type' => SpanType::ApplicationBoot],
            startTime: $time + rand(80_000_000, 160_000_000),
            endTime: $time + rand(160_000_000, 280_000_000),
        );

        $tracer->startSpan(
            name: 'Request -  /test-flare-integration',
            time: $time + rand(200_000_000, 320_000_000),
            attributes: [
                'flare.span_type' => SpanType::Request,
                'http.route' => 'test-flare-integration',
                'http.request.method' => 'GET',
                'flare.entry_point.class' => self::class,
            ],
        );

        $tracer->spanEvent(
            name: 'Glow - Hi there!',
            attributes: [
                'flare.span_event_type' => SpanEventType::Glow,
                'glow.name' => 'Hi there!',
                'glow.level' => 'info',
                'glow.context' => [],
            ],
            time: $time + rand(280_000_000, 450_000_000),
        );

        $tracer->span(
            name: 'Query - select * from users where id = ?',
            callback: function () {
            },
            attributes: [
                'flare.span_type' => SpanType::Query,
                'db.system' => 'mysql',
                'db.name' => 'default',
                'db.statement' => 'select * from users where id = ?',
                'db.sql.bindings' => ['id' => 42],
            ],
            startTime: $time + rand(250_000_000, 390_000_000),
            endTime: $time + rand(330_000_000, 510_000_000),
        );

        $tracer->endSpan(
            time: $time + rand(350_000_000, 570_000_000),
            includeMemoryUsage: true,
        );

        $tracer->endSpan(
            time: $time + rand(400_000_000, 600_000_000),
        ); // application

        return $tracer->currentTrace();
    }

    /**
     * @param array<int, Span>|null $trace
     */
    public function trace(?array $trace = null): void
    {
        $this->api->trace(
            $trace ?? $this->buildTestTrace(),
            test: true
        );
    }

    /** @return array<int, array{timeUnixNano: int, observedTimeUnixNano: int, traceId?: string, spanId?: string, flags?: string, severityText?: string, severityNumber?: int, body: mixed, attributes?: array<string, mixed>}> */
    public function buildLog(): array
    {
        $logger = $this->getTestLogger();

        $time = $this->time->getCurrentTime();

        foreach (Level::cases() as $level) {
            $logger->log(
                timestampUnixNano: $time += rand(10_000_000, 50_000_000),
                body: "This is a {$level->getName()} log message to test Flare integration.",
                severityText: strtolower($level->getName()),
                severityNumber: SeverityMapper::fromMonolog($level)
            );
        }

        return $logger->logs();
    }

    /** @param array<int, array{timeUnixNano: int, observedTimeUnixNano: int, traceId?: string, spanId?: string, flags?: string, severityText?: string, severityNumber?: int, body: mixed, attributes?: array<string, mixed>}>|null $log */
    public function log(?array $log = null): void
    {
        $this->api->log(
            $log ?? $this->buildLog(),
            test: true
        );
    }

    protected function testViaCurl(FlareEntityType $type): bool
    {
        $name = $type->singleName();

        ($this->onInfo)("Sending test {$name}...");

        try {
            $this->sendTestPayload($type);
            ($this->onInfo)("Successfully sent test {$name}");

            return true;
        } catch (BadResponseCode $e) {
            ($this->onError)("Failed to send test {$name}: {$e->getMessage()}");

            return false;
        } catch (Throwable $e) {
            ($this->onError)("Failed to send test {$name}: {$e->getMessage()}");

            return false;
        }
    }

    protected function testViaDaemon(FlareEntityType $type, int $daemonTimeout): bool
    {
        $name = $type->singleName();

        ($this->onInfo)("Sending test {$name} to daemon...");

        if ($this->sender instanceof DaemonSender) {
            $this->sender->onTestAck(function () use ($name): void {
                ($this->onInfo)("Daemon acknowledged test {$name}, waiting for Flare response...");
            });
        }

        try {
            $this->sendTestPayload($type);
            ($this->onInfo)("Successfully sent test {$name} via daemon");

            return true;
        } catch (DaemonTimeoutException) {
            ($this->onError)("Timed out waiting for Flare response for test {$name} after {$daemonTimeout} seconds");

            return false;
        } catch (BadResponseCode $e) {
            ($this->onError)("Failed to send test {$name}: {$e->getMessage()}");

            return false;
        } catch (Throwable $e) {
            ($this->onError)("Failed to send test {$name}: {$e->getMessage()}");

            return false;
        }
    }

    protected function sendTestPayload(FlareEntityType $type): void
    {
        match ($type) {
            FlareEntityType::Errors => $this->report(),
            FlareEntityType::Traces => $this->trace(),
            FlareEntityType::Logs => $this->log(),
        };
    }

    protected function getTestTracer(): Tracer
    {
        return new Tracer(
            api: $this->getDisabledApi(),
            limits: null,
            time: $this->time,
            ids: $this->ids,
            memory: $this->memory,
            recorders: new Recorders([]),
            sampler: new AlwaysSampler(),
        );
    }

    protected function getTestLogger(): Logger
    {
        return new Logger(
            api: $this->getDisabledApi(),
            time: $this->time,
            tracer: $this->getTestTracer(),
            recorders: new Recorders([]),
            disabled: false,
            minimalLogLevel: null,
        );
    }

    protected function getDisabledApi(): Api
    {
        return new class($this->api) extends Api {
            public function __construct(Api $base)
            {
                parent::__construct(
                    $base->apiToken,
                    $base->baseUrl,
                    $base->exporter,
                    $base->resource,
                    $base->scope,
                    $base->sender,
                    $base->disableQueue
                );
            }

            protected function sendEntity(FlareEntityType $type, mixed $payload, bool $immediately, bool $test = false): void
            {
                return;
            }
        };
    }
}
