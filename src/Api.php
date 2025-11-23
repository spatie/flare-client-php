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
    public const BASE_URL = 'https://ingress.flareapp.io';

    /** @var array<int, Report> */
    protected array $reportQueue = [];

    /** @var array<int, array> */
    protected array $traceQueue = [];

    public function __construct(
        protected string $apiToken,
        protected string $baseUrl,
        protected Sender $sender = new CurlSender(),
        protected bool $sendReportsImmediately = false,
    ) {
        register_shutdown_function([$this, 'sendQueue']);
    }

    public function sendReportsImmediately(bool $sendReportsImmediately = true): self
    {
        $this->sendReportsImmediately = $sendReportsImmediately;

        return $this;
    }

    public function report(
        Report $report,
        bool $immediately = false,
    ): void {
        try {
            $immediately || $this->sendReportsImmediately
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
            $immediately || $this->sendReportsImmediately
                ? $this->sendTraceToApi($trace)
                : $this->addTraceToQueue($trace);
        } catch (Exception $e) {

        }
    }

    public function test(
        Report $report
    ): void {
        $this->sendReportToApi($report, isTest: true);
    }

    public function testTrace(Array $trace): void {
        $this->sendTraceToApi($trace);
    }

    protected function addReportToQueue(Report $report): self
    {
        $this->reportQueue[] = $report;

        return $this;
    }

    protected function addTraceToQueue(array $trace): self
    {
        $this->traceQueue[] = $trace;

        return $this;
    }

    public function sendQueue(
        bool $reports = true,
        bool $traces = true,
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
    }

    protected function sendReportToApi(Report $report, bool $isTest = false): void
    {
        $payload = $this->truncateReport($report->toArray());

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
