<?php

namespace Spatie\FlareClient\Support;

use Composer\InstalledVersions;
use Exception;
use Monolog\Level;
use Spatie\FlareClient\Api;
use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\EntryPoint\EntryPointResolver;
use Spatie\FlareClient\Enums\CollectType;
use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Logger;
use Spatie\FlareClient\Memory\Memory;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Sampling\AlwaysSampler;
use Spatie\FlareClient\Senders\DaemonSender;
use Spatie\FlareClient\Senders\Exceptions\BadResponseCode;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Time\Time;
use Spatie\FlareClient\Tracer;
use Throwable;

abstract class Tester
{
    public const STYLE_PLAIN = 'plain';

    public const STYLE_INFO = 'info';

    public const STYLE_WARNING = 'warning';

    public const STYLE_ERROR = 'error';

    public function __construct(
        protected Api $api,
        protected Ids $ids,
        protected Time $time,
        protected Memory $memory,
        protected Resource $resource,
        protected ReportFactory $reportFactory,
        protected FlareConfig $config,
    ) {
    }

    abstract protected function writeLine(string $message, string $style = self::STYLE_PLAIN): void;

    abstract protected function writeNewline(): void;

    public function run(): bool
    {
        if (empty($this->config->apiToken)) {
            $this->writeLine('❌ Flare key not specified. Make sure you specify a value in the `key` setting of your Flare configuration.', self::STYLE_ERROR);

            return false;
        }

        $this->writeLine('✅ Flare key specified', self::STYLE_INFO);
        $this->writeNewline();

        $success = true;

        foreach ($this->testEntityTypes() as $entityType) {
            if (! $this->preCheckEntity($entityType)) {
                return false;
            }

            if (! $this->isEntityEnabled($entityType)) {
                $this->writeLine("❌ {$this->entityDisabledMessage($entityType)}", self::STYLE_INFO);

                continue;
            }

            $success = $this->sendTestPayload($entityType) && $success;
        }

        return $success;
    }

    protected function sendErrorPayload(): void
    {
        $time = $this->time->getCurrentTime();
        $entryPoint = $this->buildEntryPoint();

        $commandSpan = new Span(
            traceId: $this->ids->trace(),
            spanId: $this->ids->span(),
            parentSpanId: null,
            name: "Command - {$entryPoint->handlerIdentifier}",
            start: $time - 100_000_000,
            end: $time,
            attributes: [
                'flare.span_type' => SpanType::Command,
                'process.command' => $entryPoint->handlerIdentifier,
                'process.command_args' => explode(' ', $entryPoint->value),
                'process.exit_code' => 0,
                ...$entryPoint->toAttributes(),
            ],
        );

        $report = $this->reportFactory->new()
            ->throwable($this->buildThrowable())
            ->span($commandSpan)
            ->addAttributes($entryPoint->toAttributes());

        $this->api->report($report, test: true);
    }

    protected function sendTracePayload(): void
    {
        $this->api->trace(
            $this->buildTestTrace(),
            test: true
        );
    }

    protected function sendLogPayload(): void
    {
        $this->api->log(
            $this->buildLog(),
            test: true
        );
    }

    /** @return array<int, FlareEntityType> */
    protected function testEntityTypes(): array
    {
        return [FlareEntityType::Errors, FlareEntityType::Traces, FlareEntityType::Logs];
    }

    protected function preCheckEntity(FlareEntityType $type): bool
    {
        if ($type === FlareEntityType::Errors
            && $this->shouldWarnAboutStackFrameArgumentsIniSetting($this->stackFrameArgumentsEnabled())
        ) {
            $this->writeLine('⚠️ The `zend.exception_ignore_args` php ini setting is enabled. This will prevent Flare from showing stack trace arguments.', self::STYLE_WARNING);
        }

        if ($type === FlareEntityType::Logs && $this->config->sender !== DaemonSender::class) {
            $this->writeLine('⚠️ Logs are being sent without the Flare daemon. We recommend using the daemon sender for better performance and reliability.', self::STYLE_WARNING);
        }

        return true;
    }

    protected function isEntityEnabled(FlareEntityType $type): bool
    {
        return match ($type) {
            FlareEntityType::Errors => $this->config->report,
            FlareEntityType::Traces => $this->config->trace,
            FlareEntityType::Logs => $this->config->log,
        };
    }

    protected function stackFrameArgumentsEnabled(): bool
    {
        $collect = $this->config->collects[CollectType::StackFrameArguments->value] ?? null;

        if ($collect === null) {
            return false;
        }

        if ($collect['ignored'] ?? false) {
            return false;
        }

        return true;
    }

    protected function shouldWarnAboutStackFrameArgumentsIniSetting(bool $stackFrameArgumentsEnabled): bool
    {
        return $stackFrameArgumentsEnabled && (bool) ini_get('zend.exception_ignore_args');
    }

    protected function entityDisabledMessage(FlareEntityType $entityType): string
    {
        return match ($entityType) {
            FlareEntityType::Errors => 'Error reporting is disabled. Please enable the `report` option in your Flare config if you want to test it.',
            FlareEntityType::Traces => 'Tracing is disabled. Please enable the `trace` option in your Flare config if you want to test it.',
            FlareEntityType::Logs => 'Logging is disabled. Please enable the `log` option in your Flare config if you want to test it.',
        };
    }

    protected function sendTestPayload(FlareEntityType $entityType): bool
    {
        try {
            match ($entityType) {
                FlareEntityType::Errors => $this->sendErrorPayload(),
                FlareEntityType::Logs => $this->sendLogPayload(),
                FlareEntityType::Traces => $this->sendTracePayload(),
            };

            $emoji = match ($entityType) {
                FlareEntityType::Errors => '🐛',
                FlareEntityType::Logs => '📝',
                FlareEntityType::Traces => '🔍',
            };

            $entityName = ucfirst($entityType->singleName());

            $this->writeLine("{$emoji} {$entityName} sent to Flare", self::STYLE_INFO);

            return true;
        } catch (Exception $exception) {
            $this->writeLine("❌ We were unable to send a {$entityType->singleName()} to Flare. ", self::STYLE_WARNING);

            if ($exception instanceof BadResponseCode) {
                $this->writeNewline();
                $this->writeLine($this->describeBadResponse($exception), self::STYLE_WARNING);
            } else {
                $this->writeLine($exception->getMessage(), self::STYLE_WARNING);
            }

            $this->writeLine('Make sure that your key is correct and that you have a valid subscription.', self::STYLE_WARNING);
            $this->writeNewline();
            $this->writeLine('For more info visit the docs at https://flareapp.io/docs');
            $this->writeLine('You can see the status page of Flare at https://status.flareapp.io');
            $this->writeLine('Flare support can be reached at support@flareapp.io');

            $this->writeNewline();
            $this->writeEnvironmentInfo();

            return false;
        }
    }

    protected function buildThrowable(): Throwable
    {
        return new Exception('This is an exception to test if the integration with Flare works.');
    }

    /** @return array<int, Span> */
    protected function buildTestTrace(): array
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
            startTime: $time + rand(20_000_000, 40_000_000),
            endTime: $time + rand(60_000_000, 80_000_000),
        );

        $tracer->span(
            name: "Booting App",
            callback: function () {
            },
            attributes: ['flare.span_type' => SpanType::ApplicationBoot],
            startTime: $time + rand(100_000_000, 120_000_000),
            endTime: $time + rand(140_000_000, 160_000_000),
        );

        $entryPoint = $this->buildEntryPoint();

        $tracer->startSpan(
            name: "Command - {$entryPoint->handlerIdentifier}",
            time: $time + rand(180_000_000, 200_000_000),
            attributes: [
                'flare.span_type' => SpanType::Command,
                'process.command' => $entryPoint->handlerIdentifier,
                'process.command_args' => explode(' ', $entryPoint->value),
                ...$entryPoint->toAttributes(),
            ],
        );

        $tracer->span(
            name: 'Query - select * from users where id = ?',
            callback: function () {
            },
            attributes: [
                'flare.span_type' => SpanType::Query,
                'db.system' => 'mysql',
                'db.name' => 'flare',
                'db.statement' => 'select * from users where id = ?',
                'db.sql.bindings' => [42],
            ],
            startTime: $time + rand(220_000_000, 240_000_000),
            endTime: $time + rand(280_000_000, 300_000_000),
        );

        $tracer->spanEvent(
            name: 'Glow - Hi there!',
            attributes: [
                'flare.span_event_type' => SpanEventType::Glow,
                'glow.name' => 'Hi there!',
                'glow.level' => 'info',
                'glow.context' => [],
            ],
            time: $time + rand(320_000_000, 340_000_000),
        );

        $tracer->endSpan(
            time: $time + rand(360_000_000, 380_000_000),
            additionalAttributes: [
                'process.exit_code' => 0,
            ],
            includeMemoryUsage: true,
        );

        $tracer->endSpan(
            time: $time + rand(400_000_000, 420_000_000),
        );

        return $tracer->currentTrace();
    }

    /** @return array<int, array{timeUnixNano: int, observedTimeUnixNano: int, traceId?: string, spanId?: string, flags?: string, severityText?: string, severityNumber?: int, body: mixed, attributes?: array<string, mixed>}> */
    protected function buildLog(): array
    {
        $logger = $this->getTestLogger();

        $time = $this->time->getCurrentTime();

        foreach (Level::cases() as $index => $level) {
            $context = match ($index) {
                1 => ['user_id' => 42, 'tenant' => 'acme'],
                3 => ['order_id' => rand(1000, 9999), 'status' => 'pending'],
                4 => ['exception' => 'RuntimeException', 'file' => '/var/www/app.php', 'line' => 17],
                7 => ['service' => 'payments', 'attempt' => rand(1, 5)],
                default => [],
            };

            $logger->log(
                timestampUnixNano: $time += rand(10_000_000, 50_000_000),
                body: "This is a {$level->getName()} log message to test Flare integration.",
                severityText: strtolower($level->getName()),
                severityNumber: SeverityMapper::fromMonolog($level),
                attributes: ['log.context' => $context],
            );
        }

        return $logger->logs();
    }

    /** @return array<int, array{0:string, 1:string}> */
    protected function environmentInfo(): array
    {
        return [
            ['Platform', PHP_OS],
            ['PHP', phpversion()],
            ['spatie/flare-client-php', InstalledVersions::getVersion('spatie/flare-client-php') ?? 'Unknown'],
            ['Curl', curl_version()['version'] ?? 'Unknown'],
            ['SSL', curl_version()['ssl_version'] ?? 'Unknown'],
        ];
    }

    protected function writeEnvironmentInfo(): void
    {
        $rows = $this->environmentInfo();

        if ($rows === []) {
            return;
        }

        $labelWidth = max(array_map(fn (array $row) => strlen($row[0]), $rows));
        $valueWidth = max(array_map(fn (array $row) => strlen($row[1]), $rows));
        $separator = str_repeat('-', $labelWidth + 2 + $valueWidth);

        $this->writeLine($separator);

        foreach ($rows as [$label, $value]) {
            $this->writeLine(str_pad($label, $labelWidth + 2).$value);
        }

        $this->writeLine($separator);
    }

    protected function describeBadResponse(BadResponseCode $exception): string
    {
        $body = $exception->response->body;

        $message = match (true) {
            is_array($body) && isset($body['message']) => $body['message'],
            is_string($body) && $body !== '' => $body,
            default => 'Unknown error',
        };

        return "{$exception->response->code} - {$message}";
    }

    protected function getTestTracer(): Tracer
    {
        return new Tracer(
            api: $this->api,
            limits: null,
            time: $this->time,
            ids: $this->ids,
            memory: $this->memory,
            recorders: new Recorders([]),
            entryPointResolver: $this->getTestEntryPointResolver(),
            sampler: new AlwaysSampler(),
        );
    }

    protected function getTestEntryPointResolver(): EntryPointResolver
    {
        $resolver = new EntryPointResolver();
        $resolver->set($this->buildEntryPoint());

        return $resolver;
    }

    abstract protected function buildEntryPoint(): EntryPoint;

    protected function getTestLogger(): Logger
    {
        return new Logger(
            api: $this->api,
            time: $this->time,
            tracer: $this->getTestTracer(),
            recorders: new Recorders([]),
            entryPointResolver: $this->getTestEntryPointResolver(),
            disabled: false,
            minimalLogLevel: null,
        );
    }
}
