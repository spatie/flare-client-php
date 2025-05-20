<?php

namespace Spatie\FlareClient\Support;

use Spatie\FlareClient\Report;

class SentReports
{
    /** @var array<int, Report> */
    protected array $reports = [];

    public function add(Report $report): self
    {
        $this->reports[] = $report;

        return $this;
    }

    /**  @return array<int, Report> */
    public function all(): array
    {
        return $this->reports;
    }

    /** @return array<int, string> */
    public function uuids(): array
    {
        return array_filter(array_map(fn (Report $report) => $report->trackingUuid, $this->reports));
    }

    /** @return array<int, string> */
    public function urls(): array
    {
        return array_map(function (string $trackingUuid) {
            return "https://flareapp.io/tracked-occurrence/{$trackingUuid}";
        }, $this->uuids());
    }

    public function latestUuid(): ?string
    {
        return end($this->reports) ? end($this->reports)->trackingUuid : null;
    }

    public function latestUrl(): ?string
    {
        $urls = $this->urls();

        return end($urls)?: null;
    }

    public function clear(): void
    {
        $this->reports = [];
    }
}
