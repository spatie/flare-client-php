<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Spatie\FlareDaemon\NullBuffer;
use Spatie\FlareDaemon\StreamBuffer;

class StreamBufferTest extends TestCase
{
    #[Test]
    public function it_accumulates_payloads(): void
    {
        $buffer = new StreamBuffer();

        $buffer->add('{"error":"first"}');
        $buffer->add('{"error":"second"}');

        $this->assertSame(2, $buffer->count());
        $this->assertFalse($buffer->isEmpty());
    }

    #[Test]
    public function it_tracks_total_size(): void
    {
        $buffer = new StreamBuffer();

        $payload1 = '{"error":"first"}';
        $payload2 = '{"error":"second"}';

        $buffer->add($payload1);
        $buffer->add($payload2);

        $this->assertSame(strlen($payload1) + strlen($payload2), $buffer->size());
    }

    #[Test]
    public function it_detects_flush_threshold(): void
    {
        $buffer = new StreamBuffer();

        $this->assertFalse($buffer->shouldFlush());

        // Add a payload just under 6MB
        $buffer->add(str_repeat('x', 5 * 1024 * 1024));
        $this->assertFalse($buffer->shouldFlush());

        // Push over 6MB
        $buffer->add(str_repeat('x', 1 * 1024 * 1024));
        $this->assertTrue($buffer->shouldFlush());
    }

    #[Test]
    public function it_flushes_at_exactly_six_megabytes(): void
    {
        $buffer = new StreamBuffer();

        $buffer->add(str_repeat('x', 6 * 1024 * 1024));

        $this->assertTrue($buffer->shouldFlush());
    }

    #[Test]
    public function it_pulls_all_buffered_payloads(): void
    {
        $buffer = new StreamBuffer();

        $buffer->add('{"error":"first"}');
        $buffer->add('{"error":"second"}');
        $buffer->add('{"error":"third"}');

        $payloads = $buffer->pull();

        $this->assertCount(3, $payloads);
        $this->assertSame('{"error":"first"}', $payloads[0]);
        $this->assertSame('{"error":"second"}', $payloads[1]);
        $this->assertSame('{"error":"third"}', $payloads[2]);
    }

    #[Test]
    public function pull_resets_the_buffer(): void
    {
        $buffer = new StreamBuffer();

        $buffer->add('{"error":"first"}');
        $buffer->add('{"error":"second"}');

        $buffer->pull();

        $this->assertSame(0, $buffer->count());
        $this->assertSame(0, $buffer->size());
        $this->assertTrue($buffer->isEmpty());
        $this->assertFalse($buffer->shouldFlush());
        $this->assertSame([], $buffer->pull());
    }

    #[Test]
    public function it_starts_empty(): void
    {
        $buffer = new StreamBuffer();

        $this->assertTrue($buffer->isEmpty());
        $this->assertSame(0, $buffer->count());
        $this->assertSame(0, $buffer->size());
        $this->assertFalse($buffer->shouldFlush());
        $this->assertSame([], $buffer->pull());
    }

    #[Test]
    public function it_can_accumulate_after_pull(): void
    {
        $buffer = new StreamBuffer();

        $buffer->add('{"first":"batch"}');
        $first = $buffer->pull();

        $buffer->add('{"second":"batch"}');
        $second = $buffer->pull();

        $this->assertCount(1, $first);
        $this->assertSame('{"first":"batch"}', $first[0]);

        $this->assertCount(1, $second);
        $this->assertSame('{"second":"batch"}', $second[0]);
    }

    #[Test]
    public function buffers_are_independent_per_instance(): void
    {
        $buffer1 = new StreamBuffer();
        $buffer2 = new StreamBuffer();

        $buffer1->add('{"type":"errors"}');
        $buffer2->add('{"type":"traces"}');
        $buffer2->add('{"type":"traces2"}');

        $this->assertSame(1, $buffer1->count());
        $this->assertSame(2, $buffer2->count());

        $payloads1 = $buffer1->pull();
        $payloads2 = $buffer2->pull();

        $this->assertCount(1, $payloads1);
        $this->assertSame('{"type":"errors"}', $payloads1[0]);

        $this->assertCount(2, $payloads2);
        $this->assertSame('{"type":"traces"}', $payloads2[0]);
        $this->assertSame('{"type":"traces2"}', $payloads2[1]);
    }

    #[Test]
    public function null_buffer_drops_payloads(): void
    {
        $buffer = new NullBuffer();

        $buffer->add('{"error":"should be dropped"}');
        $buffer->add('{"error":"also dropped"}');

        $this->assertTrue($buffer->isEmpty());
        $this->assertSame(0, $buffer->count());
        $this->assertSame(0, $buffer->size());
        $this->assertFalse($buffer->shouldFlush());
        $this->assertSame([], $buffer->pull());
    }

    #[Test]
    public function null_buffer_never_triggers_flush(): void
    {
        $buffer = new NullBuffer();

        // Even after adding "large" payloads, shouldFlush is always false
        for ($i = 0; $i < 100; $i++) {
            $buffer->add(str_repeat('x', 100000));
        }

        $this->assertFalse($buffer->shouldFlush());
        $this->assertSame(0, $buffer->size());
    }

    #[Test]
    public function null_buffer_pull_always_returns_empty(): void
    {
        $buffer = new NullBuffer();

        $buffer->add('{"test":true}');

        $this->assertSame([], $buffer->pull());
        $this->assertSame([], $buffer->pull());
    }

    #[Test]
    public function stream_buffer_handles_many_small_payloads(): void
    {
        $buffer = new StreamBuffer();
        $payload = '{"i":0}';

        for ($i = 0; $i < 1000; $i++) {
            $buffer->add($payload);
        }

        $this->assertSame(1000, $buffer->count());
        $this->assertSame(strlen($payload) * 1000, $buffer->size());

        $payloads = $buffer->pull();
        $this->assertCount(1000, $payloads);
    }
}
