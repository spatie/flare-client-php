<?php

namespace Spatie\FlareClient;

use Exception;
use Spatie\FlareClient\Http\Client;
use Spatie\FlareClient\Truncation\ReportTrimmer;

class Api
{
    /** @var array<int, Report> */
    protected array $queue = [];

    public function __construct(
        protected Client $client,
        protected bool $sendReportsImmediately = false,
    ) {
        register_shutdown_function([$this, 'sendQueuedReports']);
    }

    public function report(Report $report): void
    {
        try {
            $this->sendReportsImmediately
                ? $this->sendReportToApi($report)
                : $this->addReportToQueue($report);
        } catch (Exception $e) {
            //
        }
    }

    public function sendTestReport(Report $report): self
    {
        $this->sendReportToApi($report);

        return $this;
    }

    protected function addReportToQueue(Report $report): self
    {
        $this->queue[] = $report;

        return $this;
    }

    public function sendQueuedReports(): void
    {
        try {
            foreach ($this->queue as $report) {
                $this->sendReportToApi($report);
            }
        } catch (Exception $e) {
            //
        } finally {
            $this->queue = [];
        }
    }

    protected function sendReportToApi(Report $report): void
    {
        $payload = $this->truncateReport($report->toArray());

        $this->client->post('reports', $payload);
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
