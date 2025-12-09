<?php

namespace Spatie\FlareClient;

use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\Exporters\Exporter;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Senders\CurlSender;
use Spatie\FlareClient\Senders\Exceptions\BadResponseCode;
use Spatie\FlareClient\Senders\Exceptions\InvalidData;
use Spatie\FlareClient\Senders\Exceptions\NotFound;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Senders\Support\Response;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Truncation\ReportTrimmer;
use Throwable;

class Api
{
    public const BASE_URL = 'https://ingress.flareapp.io';

    /** @var array<int, mixed> */
    protected array $reportQueue = [];

    /** @var array<int, mixed> */
    protected array $traceQueue = [];

    /** @var array<int, mixed> */
    protected array $logQueue = [];

    public function __construct(
        protected string $apiToken,
        protected string $baseUrl,
        protected Exporter $exporter,
        protected Resource $resource,
        protected Scope $scope,
        protected Sender $sender
    ) {

    }

    public function report(
        ReportFactory $report,
        bool $immediately = false,
        bool $test = false,
    ): array {
        $payload = $this->exporter->report($report);

        $payload = (new ReportTrimmer())->trim($payload);

        $this->sendEntity(
            type: FlareEntityType::Errors,
            payload: $payload,
            immediately: $immediately,
            test: $test
        );

        return $payload;
    }

    /** @param array<int, Span> $spans */
    public function trace(
        array $spans,
        bool $immediately = false,
        bool $test = false,
    ): array {
        $payload = $this->exporter->traces(
            $this->resource,
            $this->scope,
            $spans
        );

        $this->sendEntity(
            type: FlareEntityType::Traces,
            payload: $payload,
            immediately: $immediately,
            test: $test
        );

        return $payload;
    }

    public function log(
        array $logs,
        bool $immediately = false,
        bool $test = false,
    ): array {
        $payload = $this->exporter->logs(
            $this->resource,
            $this->scope,
            $logs
        );

        $this->sendEntity(
            type: FlareEntityType::Logs,
            payload: $payload,
            immediately: $immediately,
            test: $test
        );

        return $payload;
    }

    public function sendQueue(): void
    {
        foreach ($this->reportQueue as $report) {
            $this->sendEntity(
                FlareEntityType::Errors,
                $report,
                immediately: true,
            );
        }

        $this->reportQueue = [];

        foreach ($this->traceQueue as $trace) {
            $this->sendEntity(
                FlareEntityType::Traces,
                $trace,
                immediately: true,
            );
        }

        $this->traceQueue = [];

        foreach ($this->logQueue as $logData) {
            $this->sendEntity(
                FlareEntityType::Logs,
                $logData,
                immediately: true,
            );
        }

        $this->logQueue = [];
    }

    protected function sendEntity(FlareEntityType $type, mixed $payload, bool $immediately, bool $test = false): void
    {
        if ($immediately === false && $test === false) {
            match ($type) {
                FlareEntityType::Errors => $this->reportQueue[] = $payload,
                FlareEntityType::Traces => $this->traceQueue[] = $payload,
                FlareEntityType::Logs => $this->logQueue[] = $payload,
            };

            return;
        }

        $endpoint = match ($type) {
            FlareEntityType::Errors => "{$this->baseUrl}/v1/errors",
            FlareEntityType::Traces => "{$this->baseUrl}/v1/traces",
            FlareEntityType::Logs => "{$this->baseUrl}/v1/logs",
        };

        try {
            $this->sender->post(
                $endpoint,
                $this->apiToken,
                $payload,
                $type,
                $test,
                function (Response $response) {
                    if ($response->code === 422) {
                        throw new InvalidData($response);
                    }

                    if ($response->code === 404) {
                        throw new NotFound($response);
                    }

                    if ($response->code < 200 || $response->code >= 300) {
                        throw new BadResponseCode($response);
                    }
                }
            );
        } catch (Throwable $throwable) {
            if ($test === false) {
                return;
            }

            throw $throwable;
        }
    }
}
