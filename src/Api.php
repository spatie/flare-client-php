<?php

namespace Spatie\FlareClient;

use Exception;
use Spatie\FlareClient\Senders\CurlSender;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Truncation\ReportTrimmer;

class Api
{
    /** @var array<int, Report> */
    protected array $reportQueue = [];

    /** @var array<int, array> */
    protected array $traceQueue = [];

    public function __construct(
        protected ?string $apiToken = null,
        protected string $baseUrl = 'https://reporting.flareapp.io/api',
        protected int $timeout = 10,
        protected Sender $sender = new CurlSender(),
        protected bool $sendReportsImmediately = false,
    ) {
        register_shutdown_function([$this, 'sendQueue']);
    }

    public function sendReportsImmediately($sendReportsImmediately = true): self
    {
        $this->sendReportsImmediately = $sendReportsImmediately;

        return $this;
    }

    public function report(Report $report, bool $immediately = false): void
    {
        try {
            $immediately || $this->sendReportsImmediately
                ? $this->sendReportToApi($report)
                : $this->addReportToQueue($report);
        } catch (Exception $e) {
            //
        }
    }

    public function trace(array $trace, bool $immediately = false): void
    {
        try {
            $immediately || $this->sendReportsImmediately
                ? $this->sendTraceToApi($trace)
                : $this->addTraceToQueue($trace);
        } catch (Exception $e) {
            //
        }
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

    public function sendQueue(): void
    {
        try {
            foreach ($this->reportQueue as $report) {
                $this->sendReportToApi($report);
            }
        } catch (Exception $e) {
            //
        } finally {
            $this->reportQueue = [];
        }

        try {
            foreach ($this->traceQueue as $trace) {
                $this->sendTraceToApi($trace);
            }
        } catch (Exception $e) {
            //
        } finally {
            $this->reportQueue = [];
        }
    }

    protected function sendReportToApi(Report $report): void
    {
        $payload = $this->truncateReport($report->toArray());

        $this->sender->post(
            "{$this->baseUrl}/reports",
            $this->apiToken,
            $payload,
        );
    }

    protected function sendTraceToApi(array $trace): void
    {
        $this->sender->post(
            "{$this->baseUrl}/traces",
            $this->apiToken,
            $trace,
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
