<?php

namespace Spatie\FlareClient;

use Exception;
use Spatie\FlareClient\Enums\FlarePayloadType;
use Spatie\FlareClient\Senders\CurlSender;
use Spatie\FlareClient\Senders\Exceptions\BadResponseCode;
use Spatie\FlareClient\Senders\Exceptions\InvalidData;
use Spatie\FlareClient\Senders\Exceptions\NotFound;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Senders\Support\Response;
use Spatie\FlareClient\Truncation\ReportTrimmer;

class Api
{
    // TODO: technically doing the export here would make more sense
    // let's say we ever want to support other formats than JSON
    // like Protobuf, we could do that here before sending to the sender

    public const BASE_URL = 'https://ingress.flareapp.io';

    /** @var array<int, array> */
    protected array $reportQueue = [];

    /** @var array<int, array> */
    protected array $traceQueue = [];

    /** @var array<int, array> */
    protected array $logQueue = [];

    public function __construct(
        protected string $apiToken,
        protected string $baseUrl,
        protected Sender $sender = new CurlSender(),
    ) {

    }

    public function report(
        array $report,
        bool $immediately = false,
    ): void {
        try {
            $immediately
                ? $this->sendReportToApi($report)
                : $this->addReportToQueue($report);
        } catch (Exception $e) {

        }
    }

    public function trace(
        array $trace,
        bool $immediately = false,
    ): void {
        try {
            $immediately
                ? $this->sendTraceToApi($trace)
                : $this->addTraceToQueue($trace);
        } catch (Exception $e) {

        }
    }

    public function log(
        array $logData,
        bool $immediately = false,
    ): void {
        try {
            $immediately
                ? $this->sendLogToApi($logData)
                : $this->addLogToQueue($logData);
        } catch (Exception $e) {

        }
    }

    public function test(
        array $report
    ): void {
        $this->sendReportToApi($report, isTest: true);
    }

    protected function addReportToQueue(array $report): self
    {
        $this->reportQueue[] = $report;

        return $this;
    }

    protected function addTraceToQueue(array $trace): self
    {
        $this->traceQueue[] = $trace;

        return $this;
    }

    protected function addLogToQueue(array $logData): self
    {
        $this->logQueue[] = $logData;

        return $this;
    }

    public function sendQueue(
        bool $reports = true,
        bool $traces = true,
        bool $logs = true,
    ): void {
        if ($reports) {
            foreach ($this->reportQueue as $report) {
                try {
                    $this->sendReportToApi($report);
                } catch (Exception $e) {
                    continue;
                }
            }

            $this->reportQueue = [];
        }

        if ($traces) {
            foreach ($this->traceQueue as $trace) {
                try {
                    $this->sendTraceToApi($trace);
                } catch (Exception $e) {
                    continue;
                }
            }

            $this->traceQueue = [];
        }

        if ($logs) {
            foreach ($this->logQueue as $logData) {
                try {
                    $this->sendLogToApi($logData);
                } catch (Exception $e) {
                    continue;
                }
            }

            $this->logQueue = [];
        }
    }

    protected function sendReportToApi(array $report, bool $isTest = false): void
    {
        $payload = $this->truncateReport($report);

        $this->post(
            "{$this->baseUrl}/v1/errors",
            $payload,
            $isTest ? FlarePayloadType::TestError : FlarePayloadType::Error
        );
    }

    protected function sendTraceToApi(array $trace): void
    {
        $this->post(
            "{$this->baseUrl}/v1/traces",
            $trace,
            FlarePayloadType::Traces,
        );
    }

    protected function sendLogToApi(array $logData): void
    {
        $this->post(
            "{$this->baseUrl}/v1/logs",
            $logData,
            FlarePayloadType::Logs,
        );
    }

    protected function post(
        string $endpoint,
        array $payload,
        FlarePayloadType $type,
    ): void {
        $this->sender->post(
            $endpoint,
            $this->apiToken,
            $payload,
            $type,
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
    }

    /**
     * @param array<int|string, mixed> $payload
     *
     * @return array<int|string, mixed>
     */
    protected function truncateReport(array $payload): array
    {
        return (new ReportTrimmer())->trim($payload);
    }
}
