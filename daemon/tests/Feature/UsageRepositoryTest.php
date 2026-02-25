<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Spatie\FlareDaemon\UsageRepository;
use Tests\Response;
use Tests\TestCase;

class UsageRepositoryTest extends TestCase
{
    private UsageRepository $usageRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usageRepo = $this->createUsageRepository();
    }

    // --- Startup and quota fetch ---

    #[Test]
    public function it_fetches_usage_on_startup(): void
    {
        $this->browser->queueResponse('/v1/usage', Response::usageResponse(
            errorsUsed: 50,
            errorsLimit: 1000,
            tracesUsed: 100,
            tracesLimit: 1000,
            logsUsed: 200,
            logsLimit: 1000,
        ));

        $this->usageRepo->start();

        $this->browser->assertGetCount(1);
        $this->browser->assertRequestedUrl('/v1/usage');

        $usage = $this->usageRepo->usage();
        $this->assertNotNull($usage);
        $this->assertSame(50, $usage->errorsUsed);
        $this->assertSame(1000, $usage->errorsLimit);
        $this->assertSame(100, $usage->tracesUsed);
        $this->assertSame(200, $usage->logsUsed);
    }

    #[Test]
    public function it_pauses_over_limit_types_on_startup(): void
    {
        $this->browser->queueResponse('/v1/usage', Response::usageResponse(
            errorsUsed: 1000,
            errorsLimit: 1000,
            tracesUsed: 500,
            tracesLimit: 1000,
            logsUsed: 200,
            logsLimit: 1000,
        ));

        $this->usageRepo->start();

        $this->assertTrue($this->ingest->isPaused('errors'));
        $this->assertFalse($this->ingest->isPaused('traces'));
        $this->assertFalse($this->ingest->isPaused('logs'));
    }

    #[Test]
    public function it_pauses_multiple_over_limit_types_on_startup(): void
    {
        $this->browser->queueResponse('/v1/usage', Response::usageResponse(
            errorsUsed: 1000,
            errorsLimit: 1000,
            tracesUsed: 2000,
            tracesLimit: 1000,
            logsUsed: 200,
            logsLimit: 1000,
        ));

        $this->usageRepo->start();

        $this->assertTrue($this->ingest->isPaused('errors'));
        $this->assertTrue($this->ingest->isPaused('traces'));
        $this->assertFalse($this->ingest->isPaused('logs'));
    }

    // --- Daily refresh ---

    #[Test]
    public function it_refreshes_usage_every_24_hours(): void
    {
        $this->browser->queueResponse('/v1/usage', Response::usageResponse(
            errorsUsed: 50,
            errorsLimit: 1000,
        ));

        $this->usageRepo->start();

        $this->browser->assertGetCount(1);

        // Queue a second response for the daily refresh
        $this->browser->queueResponse('/v1/usage', Response::usageResponse(
            errorsUsed: 200,
            errorsLimit: 1000,
        ));

        // Advance 24 hours
        $this->advanceTime(86400.0);

        $this->browser->assertGetCount(2);

        $usage = $this->usageRepo->usage();
        $this->assertNotNull($usage);
        $this->assertSame(200, $usage->errorsUsed);
    }

    // --- Local counter tracking ---

    #[Test]
    public function it_tracks_local_counters_and_pauses_when_limit_reached(): void
    {
        $this->browser->queueResponse('/v1/usage', Response::usageResponse(
            errorsUsed: 990,
            errorsLimit: 1000,
        ));

        $this->usageRepo->start();

        $this->assertFalse($this->ingest->isPaused('errors'));

        // Increment local counter to reach limit (990 used + 10 local = 1000 limit)
        $this->usageRepo->incrementLocal('errors', 10);

        $this->assertTrue($this->ingest->isPaused('errors'));
        $this->assertTrue($this->output->hasMessage('Local counter reached limit for errors'));
    }

    #[Test]
    public function it_does_not_pause_when_local_counter_below_limit(): void
    {
        $this->browser->queueResponse('/v1/usage', Response::usageResponse(
            errorsUsed: 990,
            errorsLimit: 1000,
        ));

        $this->usageRepo->start();

        $this->usageRepo->incrementLocal('errors', 5);

        $this->assertFalse($this->ingest->isPaused('errors'));
    }

    #[Test]
    public function it_increments_via_ingest_callback(): void
    {
        $this->browser->queueResponse('/v1/usage', Response::usageResponse(
            errorsUsed: 995,
            errorsLimit: 1000,
        ));

        $this->usageRepo->start();

        // Trigger the ingest onUsageIncrement callback (simulating a successful send)
        $this->ingest->buffer('errors', '{"error":"e1"}');
        $this->ingest->buffer('errors', '{"error":"e2"}');
        $this->ingest->buffer('errors', '{"error":"e3"}');
        $this->ingest->buffer('errors', '{"error":"e4"}');
        $this->ingest->buffer('errors', '{"error":"e5"}');

        // Queue a 201 response for the flush
        $this->browser->queueResponse('/v1/errors', Response::created());

        $this->ingest->startFlushTimers();
        $this->advanceTime(10.0);

        // The onUsageIncrement callback should have been triggered with count=5
        // 995 + 5 = 1000 >= 1000 limit, so errors should be paused
        $this->assertTrue($this->ingest->isPaused('errors'));
    }

    // --- Per-type independence ---

    #[Test]
    public function error_limit_does_not_pause_traces_or_logs(): void
    {
        $this->browser->queueResponse('/v1/usage', Response::usageResponse(
            errorsUsed: 999,
            errorsLimit: 1000,
            tracesUsed: 100,
            tracesLimit: 1000,
            logsUsed: 100,
            logsLimit: 1000,
        ));

        $this->usageRepo->start();

        $this->usageRepo->incrementLocal('errors', 1);

        $this->assertTrue($this->ingest->isPaused('errors'));
        $this->assertFalse($this->ingest->isPaused('traces'));
        $this->assertFalse($this->ingest->isPaused('logs'));
    }

    // --- Response-driven stop on 429 quota exceeded ---

    #[Test]
    public function it_pauses_type_and_re_fetches_usage_on_quota_exceeded(): void
    {
        $this->browser->queueResponse('/v1/usage', Response::usageResponse(
            errorsUsed: 900,
            errorsLimit: 1000,
        ));

        $this->usageRepo->start();

        $this->assertFalse($this->ingest->isPaused('errors'));

        // Queue a usage response for the re-fetch triggered by quota exceeded
        $this->browser->queueResponse('/v1/usage', Response::usageResponse(
            errorsUsed: 1000,
            errorsLimit: 1000,
        ));

        // Simulate quota exceeded event from Ingest
        $this->usageRepo->onQuotaExceeded('errors');

        $this->assertTrue($this->ingest->isPaused('errors'));

        // The 0.1s timer for re-fetch should fire
        $this->advanceTime(0.1);

        // Initial GET + re-fetch GET = at least 2
        $this->assertGreaterThanOrEqual(2, count($this->browser->getRequests()));
    }

    // --- Resume when re-fetch shows under quota ---

    #[Test]
    public function it_resumes_type_when_re_fetch_shows_under_quota(): void
    {
        // Start with errors over limit
        $this->browser->queueResponse('/v1/usage', Response::usageResponse(
            errorsUsed: 1000,
            errorsLimit: 1000,
        ));

        $this->usageRepo->start();

        $this->assertTrue($this->ingest->isPaused('errors'));

        // Queue a new usage response that shows errors under limit
        $this->browser->queueResponse('/v1/usage', Response::usageResponse(
            errorsUsed: 500,
            errorsLimit: 1000,
        ));

        // Advance to daily refresh
        $this->advanceTime(86400.0);

        $this->assertFalse($this->ingest->isPaused('errors'));
        $this->assertTrue($this->output->hasMessage('Resumed ingestion for errors'));
    }

    // --- Local counters reset on fetch ---

    #[Test]
    public function it_resets_local_counters_on_successful_fetch(): void
    {
        $this->browser->queueResponse('/v1/usage', Response::usageResponse(
            errorsUsed: 950,
            errorsLimit: 1000,
        ));

        $this->usageRepo->start();

        // Increment local counter
        $this->usageRepo->incrementLocal('errors', 30);

        $this->assertFalse($this->ingest->isPaused('errors'));

        // Fetch again — local counters should reset
        $this->browser->queueResponse('/v1/usage', Response::usageResponse(
            errorsUsed: 980,
            errorsLimit: 1000,
        ));

        $this->advanceTime(86400.0);

        // After re-fetch, local counters are zero, so 980/1000 is not over limit
        $this->assertFalse($this->ingest->isPaused('errors'));

        // A small local increment should not push over now
        $this->usageRepo->incrementLocal('errors', 10);
        $this->assertFalse($this->ingest->isPaused('errors'));

        // But enough to reach the limit should
        $this->usageRepo->incrementLocal('errors', 10);
        $this->assertTrue($this->ingest->isPaused('errors'));
    }

    // --- Retry strategies ---

    #[Test]
    public function it_retries_with_exponential_backoff_before_first_success(): void
    {
        // All fetches fail
        $this->browser->setDefaultError(new \RuntimeException('Connection refused'));

        $this->usageRepo->start();

        $this->browser->assertGetCount(1);
        $this->assertTrue($this->output->hasMessage('Usage fetch failed'));

        // Backoff schedule: 2.5, 5, 10, 15, 30, 60, 120, 240

        // After 2.5s, should retry
        $this->advanceTime(2.5);
        $this->browser->assertGetCount(2);

        // After another 5s, should retry
        $this->advanceTime(5.0);
        $this->browser->assertGetCount(3);

        // After another 10s
        $this->advanceTime(10.0);
        $this->browser->assertGetCount(4);

        // After another 15s
        $this->advanceTime(15.0);
        $this->browser->assertGetCount(5);

        // After another 30s
        $this->advanceTime(30.0);
        $this->browser->assertGetCount(6);

        // After another 60s
        $this->advanceTime(60.0);
        $this->browser->assertGetCount(7);

        // After another 120s
        $this->advanceTime(120.0);
        $this->browser->assertGetCount(8);

        // After another 240s
        $this->advanceTime(240.0);
        $this->browser->assertGetCount(9);

        // After backoff schedule exhausted, should use 300s intervals (12 times)
        $this->advanceTime(300.0);
        $this->browser->assertGetCount(10);
    }

    #[Test]
    public function it_uses_300s_intervals_after_backoff_schedule_exhausted(): void
    {
        $this->browser->setDefaultError(new \RuntimeException('Connection refused'));

        $this->usageRepo->start();

        // Exhaust the initial backoff schedule (8 retries)
        $this->advanceTime(2.5);  // retry 1
        $this->advanceTime(5.0);  // retry 2
        $this->advanceTime(10.0); // retry 3
        $this->advanceTime(15.0); // retry 4
        $this->advanceTime(30.0); // retry 5
        $this->advanceTime(60.0); // retry 6
        $this->advanceTime(120.0); // retry 7
        $this->advanceTime(240.0); // retry 8

        $countAfterBackoff = count($this->browser->getRequests());

        // Now should use 300s intervals
        $this->advanceTime(300.0);
        $this->assertSame($countAfterBackoff + 1, count($this->browser->getRequests()));

        $this->advanceTime(300.0);
        $this->assertSame($countAfterBackoff + 2, count($this->browser->getRequests()));
    }

    #[Test]
    public function it_switches_to_3600s_after_12_300s_retries(): void
    {
        $this->browser->setDefaultError(new \RuntimeException('Connection refused'));

        $this->usageRepo->start();

        // Exhaust the initial backoff schedule (8 retries)
        $backoffs = [2.5, 5.0, 10.0, 15.0, 30.0, 60.0, 120.0, 240.0];
        foreach ($backoffs as $delay) {
            $this->advanceTime($delay);
        }

        // 12 retries at 300s
        for ($i = 0; $i < 12; $i++) {
            $this->advanceTime(300.0);
        }

        $countBefore3600 = count($this->browser->getRequests());

        // Next retry should be at 3600s, not 300s
        $this->advanceTime(300.0);
        $this->assertSame($countBefore3600, count($this->browser->getRequests()));

        $this->advanceTime(3300.0); // total 3600s from last
        $this->assertSame($countBefore3600 + 1, count($this->browser->getRequests()));
    }

    #[Test]
    public function it_retries_every_300s_after_first_success(): void
    {
        // First fetch succeeds
        $this->browser->queueResponse('/v1/usage', Response::usageResponse());

        $this->usageRepo->start();

        $this->assertNotNull($this->usageRepo->usage());

        // Now make fetches fail
        $this->browser->setDefaultError(new \RuntimeException('Temporary failure'));

        // Trigger a quota exceeded to force a re-fetch
        $this->usageRepo->onQuotaExceeded('errors');
        $this->advanceTime(0.1); // scheduleFetch timer

        $countAfterFirstFailure = count($this->browser->getRequests());

        // Should retry at 300s intervals
        $this->advanceTime(300.0);
        $this->assertSame($countAfterFirstFailure + 1, count($this->browser->getRequests()));

        $this->advanceTime(300.0);
        $this->assertSame($countAfterFirstFailure + 2, count($this->browser->getRequests()));
    }

    #[Test]
    public function it_switches_to_3600s_after_13_failures_post_success(): void
    {
        // First fetch succeeds
        $this->browser->queueResponse('/v1/usage', Response::usageResponse());

        $this->usageRepo->start();

        // Now make fetches fail
        $this->browser->setDefaultError(new \RuntimeException('Temporary failure'));

        // Trigger re-fetch via quota exceeded
        $this->usageRepo->onQuotaExceeded('errors');
        $this->advanceTime(0.1); // scheduleFetch fires
        // That's failure 1

        // 12 more failures at 300s each (failures 2-13)
        for ($i = 0; $i < 12; $i++) {
            $this->advanceTime(300.0);
        }

        $countBefore3600 = count($this->browser->getRequests());

        // 14th failure should be at 3600s
        $this->advanceTime(300.0);
        $this->assertSame($countBefore3600, count($this->browser->getRequests()));

        $this->advanceTime(3300.0);
        $this->assertSame($countBefore3600 + 1, count($this->browser->getRequests()));
    }

    // --- Reset at scheduling ---

    #[Test]
    public function it_schedules_fetch_at_reset_time_when_all_over_limit(): void
    {
        // Set clock to a known time so we can calculate reset_at
        $resetTimestamp = (int) $this->clock->now() + 3600; // 1 hour from now
        $resetAt = date('Y-m-d H:i:s', $resetTimestamp);

        $this->browser->queueResponse('/v1/usage', Response::usageResponse(
            errorsUsed: 1000,
            errorsLimit: 1000,
            tracesUsed: 1000,
            tracesLimit: 1000,
            logsUsed: 1000,
            logsLimit: 1000,
            resetAt: $resetAt,
        ));

        $this->usageRepo->start();

        $this->assertTrue($this->ingest->isPaused('errors'));
        $this->assertTrue($this->ingest->isPaused('traces'));
        $this->assertTrue($this->ingest->isPaused('logs'));

        // Queue a response for the scheduled reset fetch
        $this->browser->queueResponse('/v1/usage', Response::usageResponse(
            errorsUsed: 0,
            errorsLimit: 1000,
            tracesUsed: 0,
            tracesLimit: 1000,
            logsUsed: 0,
            logsLimit: 1000,
        ));

        // Advance to the reset time
        $this->advanceTime(3600.0);

        // Should have re-fetched and resumed all types
        $this->assertFalse($this->ingest->isPaused('errors'));
        $this->assertFalse($this->ingest->isPaused('traces'));
        $this->assertFalse($this->ingest->isPaused('logs'));
    }

    // --- Fetch failure handling ---

    #[Test]
    public function it_handles_non_200_usage_response(): void
    {
        $this->browser->queueResponse('/v1/usage', Response::serverError());

        $this->usageRepo->start();

        $this->assertNull($this->usageRepo->usage());
        $this->assertTrue($this->output->hasMessage('Usage fetch returned 500'));
        $this->assertTrue($this->output->hasMessage('Usage fetch failed'));
    }

    #[Test]
    public function it_handles_invalid_json_usage_response(): void
    {
        $this->browser->queueResponse('/v1/usage', Response::ok('not json'));

        $this->usageRepo->start();

        $this->assertNull($this->usageRepo->usage());
        $this->assertTrue($this->output->hasMessage('Usage fetch returned invalid JSON'));
    }

    #[Test]
    public function it_handles_network_error_on_fetch(): void
    {
        $this->browser->setDefaultError(new \RuntimeException('DNS resolution failed'));

        $this->usageRepo->start();

        $this->assertNull($this->usageRepo->usage());
        $this->assertTrue($this->output->hasMessage('Usage fetch failed'));
        $this->assertTrue($this->output->hasMessage('DNS resolution failed'));
    }

    // --- Increment without prior usage fetch ---

    #[Test]
    public function it_tracks_local_counters_before_first_successful_fetch(): void
    {
        // Fetch fails
        $this->browser->setDefaultError(new \RuntimeException('Down'));

        $this->usageRepo->start();

        // Increment should not cause a pause (no usage data to compare against)
        $this->usageRepo->incrementLocal('errors', 100);

        $this->assertFalse($this->ingest->isPaused('errors'));
    }
}
