<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Spatie\FlareDaemon\Ingest;
use Spatie\FlareDaemon\UsageRepository;

abstract class TestCase extends BaseTestCase
{
    protected LoopFake $loop;

    protected BrowserFake $browser;

    protected SyncedClock $clock;

    protected OutputWriterFake $output;

    protected Ingest $ingest;

    protected const API_KEY = 'test-api-key';

    protected const BASE_URL = 'https://ingress.flareapp.io';

    protected function setUp(): void
    {
        parent::setUp();

        $this->loop = new LoopFake();
        $this->browser = new BrowserFake();
        $this->clock = new SyncedClock($this->loop);

        $this->output = new OutputWriterFake();

        $this->ingest = new Ingest(
            $this->loop,
            $this->browser,
            $this->output,
            self::API_KEY,
            self::BASE_URL,
        );
    }

    protected function createUsageRepository(): UsageRepository
    {
        return new UsageRepository(
            $this->loop,
            $this->browser,
            $this->output,
            $this->ingest,
            $this->clock,
            self::API_KEY,
            self::BASE_URL,
        );
    }

    protected function createTcpServerFake(): TcpServerFake
    {
        return new TcpServerFake($this->ingest);
    }

    /**
     * Build a length-prefixed TCP frame for a payload.
     */
    protected function buildFrame(string $type, string $data): string
    {
        return PendingConnection::buildFrame($type, $data);
    }

    /**
     * Create a new pending connection builder.
     */
    protected function pendingConnection(): PendingConnection
    {
        return new PendingConnection();
    }

    /**
     * Advance the fake loop time and process all due timers.
     */
    protected function advanceTime(float $seconds): void
    {
        $this->loop->advance($seconds);
    }
}
