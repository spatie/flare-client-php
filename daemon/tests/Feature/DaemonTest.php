<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Spatie\FlareDaemon\CheckForUpdates;
use Tests\Connection;
use Tests\Response;
use Tests\TestCase;

class DaemonTest extends TestCase
{
    // --- Startup sequence ---

    #[Test]
    public function it_wires_startup_sequence_correctly(): void
    {
        $this->browser->queueResponse('/v1/usage', Response::usageResponse(
            errorsUsed: 50,
            errorsLimit: 1000,
        ));

        $usageRepo = $this->createUsageRepository();
        $tcpServer = $this->createTcpServerFake();

        // Simulate the daemon.php startup sequence
        $this->ingest->startFlushTimers();
        $usageRepo->start();

        // Usage should have been fetched
        $this->browser->assertGetCount(1);
        $this->assertNotNull($usageRepo->usage());

        // Verify ingest can buffer and flush
        $this->ingest->buffer('errors', '{"error":"startup-test"}');
        $this->browser->queueResponse('/v1/errors', Response::created());
        $this->advanceTime(10.0);

        // Initial GET + flush POST
        $this->assertSame(2, $this->browser->requestCount());
    }

    #[Test]
    public function it_integrates_server_payloads_with_ingest_and_usage(): void
    {
        $this->browser->queueResponse('/v1/usage', Response::usageResponse(
            errorsUsed: 995,
            errorsLimit: 1000,
        ));

        $usageRepo = $this->createUsageRepository();
        $tcpServer = $this->createTcpServerFake();

        $this->ingest->startFlushTimers();
        $usageRepo->start();

        // Send payloads via the TCP server fake (buffers directly into Ingest)
        $this->browser->queueResponse('/v1/errors', Response::created());

        $tcpServer->sendPayload('errors', '{"error":"e1"}');
        $tcpServer->sendPayload('errors', '{"error":"e2"}');
        $tcpServer->sendPayload('errors', '{"error":"e3"}');
        $tcpServer->sendPayload('errors', '{"error":"e4"}');
        $tcpServer->sendPayload('errors', '{"error":"e5"}');

        $this->advanceTime(10.0);

        // 5 errors should push 995 + 5 = 1000 >= 1000
        $this->assertTrue($this->ingest->isPaused('errors'));

        // Traces and logs remain unaffected
        $this->assertFalse($this->ingest->isPaused('traces'));
        $this->assertFalse($this->ingest->isPaused('logs'));
    }

    // --- Graceful shutdown ---

    #[Test]
    public function it_performs_graceful_shutdown(): void
    {
        $this->browser->queueResponse('/v1/usage', Response::usageResponse());

        $usageRepo = $this->createUsageRepository();
        $tcpServer = $this->createTcpServerFake();

        $this->ingest->startFlushTimers();
        $usageRepo->start();

        // Buffer some payloads
        $this->ingest->buffer('errors', '{"error":"e1"}');
        $this->ingest->buffer('traces', '{"trace":"t1"}');

        // Simulate graceful shutdown (like SIGTERM handler in daemon.php)
        $resolved = false;
        $this->ingest->forceDigest()->then(function () use (&$resolved): void {
            $this->loop->stop();
            $resolved = true;
        });

        $this->assertTrue($resolved);

        // Verify all buffered payloads were flushed
        $postRequests = $this->browser->postRequests();
        $this->assertGreaterThanOrEqual(2, count($postRequests));

        // Loop should be stopped
        $this->assertFalse($this->loop->running());
    }

    #[Test]
    public function it_rejects_new_payloads_after_shutdown(): void
    {
        $this->browser->queueResponse('/v1/usage', Response::usageResponse());

        $usageRepo = $this->createUsageRepository();

        $this->ingest->startFlushTimers();
        $usageRepo->start();

        // Shutdown
        $this->ingest->forceDigest()->then(function (): void {
            $this->loop->stop();
        });

        // New payloads should be rejected
        $postCountBefore = count($this->browser->postRequests());
        $this->ingest->buffer('errors', '{"error":"rejected"}');

        $this->advanceTime(10.0);

        $this->assertSame($postCountBefore, count($this->browser->postRequests()));
    }

    #[Test]
    public function it_flushes_pending_test_payloads_on_shutdown(): void
    {
        $this->browser->queueResponse('/v1/usage', Response::usageResponse());

        $usageRepo = $this->createUsageRepository();

        $this->ingest->startFlushTimers();
        $usageRepo->start();

        $connection = new Connection();

        $this->browser->queueResponse('/v1/errors', Response::created('{"test":"ok"}'));

        $this->ingest->bufferTest('errors', '{"test":"pending"}', $connection);

        // Force digest should handle the test payload
        $resolved = false;
        $this->ingest->forceDigest()->then(function () use (&$resolved): void {
            $resolved = true;
        });

        $this->assertTrue($resolved);

        // Test connection should have received a response
        $written = $connection->writtenData();
        $this->assertNotEmpty($written);
    }

    // --- CheckForUpdates / composer.lock change detection ---

    #[Test]
    public function it_detects_composer_lock_changes_and_initiates_shutdown(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'composer_lock_test_');
        $this->assertNotFalse($tempFile);

        try {
            file_put_contents($tempFile, '{"packages":[]}');

            $checkForUpdates = new CheckForUpdates(
                $this->loop,
                $this->output,
                $this->ingest,
                $tempFile,
            );

            $checkForUpdates->start();

            // No shutdown should be scheduled yet
            $this->assertFalse($this->output->hasMessage('composer.lock changed'));

            // Change the composer.lock file
            file_put_contents($tempFile, '{"packages":["new-package"]}');

            // Advance 60s to trigger the check
            $this->advanceTime(60.0);

            $this->assertTrue($this->output->hasMessage('composer.lock changed'));
            $this->assertTrue($this->output->hasMessage('graceful shutdown in 5 minutes'));

            // Advance through the 5-minute countdown (5 x 60s intervals)
            // First tick at 60s: "Shutting down in 4 minute(s)..."
            $this->advanceTime(60.0);
            $this->assertTrue($this->output->hasMessage('Shutting down in 4 minute(s)'));

            $this->advanceTime(60.0);
            $this->assertTrue($this->output->hasMessage('Shutting down in 3 minute(s)'));

            $this->advanceTime(60.0);
            $this->assertTrue($this->output->hasMessage('Shutting down in 2 minute(s)'));

            $this->advanceTime(60.0);
            $this->assertTrue($this->output->hasMessage('Shutting down in 1 minute(s)'));

            // Final tick — performs shutdown
            $this->advanceTime(60.0);
            $this->assertTrue($this->output->hasMessage('Shutting down — flushing buffers'));
            $this->assertTrue($this->output->hasMessage('All buffers flushed'));
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function it_does_not_trigger_shutdown_when_file_unchanged(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'composer_lock_test_');
        $this->assertNotFalse($tempFile);

        try {
            file_put_contents($tempFile, '{"packages":[]}');

            $checkForUpdates = new CheckForUpdates(
                $this->loop,
                $this->output,
                $this->ingest,
                $tempFile,
            );

            $checkForUpdates->start();

            // Advance several check intervals
            $this->advanceTime(60.0);
            $this->advanceTime(60.0);
            $this->advanceTime(60.0);

            $this->assertFalse($this->output->hasMessage('composer.lock changed'));
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function it_handles_temporarily_unreadable_composer_lock(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'composer_lock_test_');
        $this->assertNotFalse($tempFile);

        try {
            file_put_contents($tempFile, '{"packages":[]}');

            $checkForUpdates = new CheckForUpdates(
                $this->loop,
                $this->output,
                $this->ingest,
                $tempFile,
            );

            $checkForUpdates->start();

            // Remove the file temporarily
            unlink($tempFile);

            // Check should be skipped (no shutdown triggered)
            $this->advanceTime(60.0);

            $this->assertFalse($this->output->hasMessage('composer.lock changed'));

            // Restore the file with the same contents
            file_put_contents($tempFile, '{"packages":[]}');

            $this->advanceTime(60.0);

            // Still no shutdown because hash matches
            $this->assertFalse($this->output->hasMessage('composer.lock changed'));
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function it_skips_update_checking_when_no_path_configured(): void
    {
        // CheckForUpdates with empty path would not be created in daemon.php
        // This tests that no path means no monitoring
        // In daemon.php: if ($composerLockPath !== '') { new CheckForUpdates(...) }

        // Just verify no exceptions when the file doesn't exist
        $checkForUpdates = new CheckForUpdates(
            $this->loop,
            $this->output,
            $this->ingest,
            '/nonexistent/path/composer.lock',
        );

        $checkForUpdates->start();

        $this->assertTrue($this->output->hasMessage('Could not read composer.lock'));

        $this->advanceTime(60.0);

        // No shutdown triggered
        $this->assertFalse($this->output->hasMessage('composer.lock changed'));
    }

    #[Test]
    public function it_establishes_baseline_on_first_successful_read(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'composer_lock_test_');
        $this->assertNotFalse($tempFile);

        try {
            // Start with no file
            unlink($tempFile);

            $checkForUpdates = new CheckForUpdates(
                $this->loop,
                $this->output,
                $this->ingest,
                $tempFile,
            );

            $checkForUpdates->start();

            $this->assertTrue($this->output->hasMessage('Could not read composer.lock'));

            // Now create the file
            file_put_contents($tempFile, '{"packages":[]}');

            // First successful read should establish baseline, NOT trigger shutdown
            $this->advanceTime(60.0);

            $this->assertFalse($this->output->hasMessage('composer.lock changed'));

            // Subsequent read with same content should not trigger shutdown
            $this->advanceTime(60.0);

            $this->assertFalse($this->output->hasMessage('composer.lock changed'));

            // Change it — NOW it should trigger
            file_put_contents($tempFile, '{"packages":["new"]}');

            $this->advanceTime(60.0);

            $this->assertTrue($this->output->hasMessage('composer.lock changed'));
        } finally {
            @unlink($tempFile);
        }
    }
}
