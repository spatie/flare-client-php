<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Spatie\FlareDaemon\Payload;

class PayloadTest extends TestCase
{
    #[Test]
    public function it_parses_a_complete_payload(): void
    {
        $payload = new Payload();

        $data = '{"error":"test"}';
        $message = "v1:errors:{$data}";
        $frame = strlen($message) . ":{$message}";

        $payload->append($frame);

        $this->assertTrue($payload->isComplete());
        $this->assertTrue($payload->isValid());
        $this->assertSame('v1', $payload->version());
        $this->assertSame('errors', $payload->type());
        $this->assertSame($data, $payload->data());
        $this->assertFalse($payload->isTest());
        $this->assertSame('errors', $payload->baseType());
    }

    #[Test]
    public function it_handles_chunked_data(): void
    {
        $payload = new Payload();

        $data = '{"error":"test"}';
        $message = "v1:errors:{$data}";
        $frame = strlen($message) . ":{$message}";

        $chunk1 = substr($frame, 0, 10);
        $chunk2 = substr($frame, 10, 10);
        $chunk3 = substr($frame, 20);

        $payload->append($chunk1);
        $this->assertFalse($payload->isComplete());

        $payload->append($chunk2);
        $this->assertFalse($payload->isComplete());

        $payload->append($chunk3);
        $this->assertTrue($payload->isComplete());
        $this->assertTrue($payload->isValid());
        $this->assertSame('errors', $payload->type());
        $this->assertSame($data, $payload->data());
    }

    #[Test]
    public function it_rejects_invalid_version(): void
    {
        $payload = new Payload();

        $data = '{"error":"test"}';
        $message = "v2:errors:{$data}";
        $frame = strlen($message) . ":{$message}";

        $payload->append($frame);

        $this->assertTrue($payload->isComplete());
        $this->assertFalse($payload->isValid());
        $this->assertSame('v2', $payload->version());
        $this->assertNull($payload->type());
        $this->assertNull($payload->data());
    }

    #[Test]
    public function it_identifies_test_types(): void
    {
        $testTypes = ['errors_test', 'traces_test', 'logs_test'];

        foreach ($testTypes as $type) {
            $payload = new Payload();

            $data = '{"test":true}';
            $message = "v1:{$type}:{$data}";
            $frame = strlen($message) . ":{$message}";

            $payload->append($frame);

            $this->assertTrue($payload->isComplete());
            $this->assertTrue($payload->isValid());
            $this->assertTrue($payload->isTest(), "Expected {$type} to be identified as test");
        }
    }

    #[Test]
    public function it_identifies_non_test_types(): void
    {
        $normalTypes = ['errors', 'traces', 'logs'];

        foreach ($normalTypes as $type) {
            $payload = new Payload();

            $data = '{"test":false}';
            $message = "v1:{$type}:{$data}";
            $frame = strlen($message) . ":{$message}";

            $payload->append($frame);

            $this->assertTrue($payload->isComplete());
            $this->assertFalse($payload->isTest(), "Expected {$type} to NOT be identified as test");
        }
    }

    #[Test]
    public function it_extracts_base_type_from_test_types(): void
    {
        $cases = [
            'errors_test' => 'errors',
            'traces_test' => 'traces',
            'logs_test' => 'logs',
        ];

        foreach ($cases as $type => $expectedBase) {
            $payload = new Payload();

            $data = '{"test":true}';
            $message = "v1:{$type}:{$data}";
            $frame = strlen($message) . ":{$message}";

            $payload->append($frame);

            $this->assertSame($expectedBase, $payload->baseType(), "Expected base type of {$type} to be {$expectedBase}");
        }
    }

    #[Test]
    public function it_returns_type_as_base_type_for_non_test_types(): void
    {
        $payload = new Payload();

        $data = '{"error":"test"}';
        $message = "v1:errors:{$data}";
        $frame = strlen($message) . ":{$message}";

        $payload->append($frame);

        $this->assertSame('errors', $payload->baseType());
    }

    #[Test]
    public function it_handles_sequential_parsing_on_persistent_connections(): void
    {
        $payload = new Payload();

        $data1 = '{"error":"first"}';
        $message1 = "v1:errors:{$data1}";
        $frame1 = strlen($message1) . ":{$message1}";

        $data2 = '{"trace":"second"}';
        $message2 = "v1:traces:{$data2}";
        $frame2 = strlen($message2) . ":{$message2}";

        // Send both frames concatenated
        $payload->append($frame1 . $frame2);

        // First payload should be complete
        $this->assertTrue($payload->isComplete());
        $this->assertTrue($payload->isValid());
        $this->assertSame('errors', $payload->type());
        $this->assertSame($data1, $payload->data());
        $this->assertNotEmpty($payload->overflow());

        // Reset and parse next payload from overflow
        $payload->reset();

        $this->assertTrue($payload->isComplete());
        $this->assertTrue($payload->isValid());
        $this->assertSame('traces', $payload->type());
        $this->assertSame($data2, $payload->data());
        $this->assertEmpty($payload->overflow());
    }

    #[Test]
    public function it_handles_sequential_parsing_with_chunked_overflow(): void
    {
        $payload = new Payload();

        $data1 = '{"error":"first"}';
        $message1 = "v1:errors:{$data1}";
        $frame1 = strlen($message1) . ":{$message1}";

        $data2 = '{"trace":"second"}';
        $message2 = "v1:traces:{$data2}";
        $frame2 = strlen($message2) . ":{$message2}";

        // Send first frame + partial second frame
        $partial = substr($frame2, 0, 10);
        $payload->append($frame1 . $partial);

        $this->assertTrue($payload->isComplete());
        $this->assertSame('errors', $payload->type());
        $this->assertSame($data1, $payload->data());

        // Reset - overflow should contain partial second frame
        $payload->reset();
        $this->assertFalse($payload->isComplete());

        // Send remainder
        $payload->append(substr($frame2, 10));

        $this->assertTrue($payload->isComplete());
        $this->assertTrue($payload->isValid());
        $this->assertSame('traces', $payload->type());
        $this->assertSame($data2, $payload->data());
    }

    #[Test]
    public function it_handles_large_payloads(): void
    {
        $payload = new Payload();

        $data = '{"data":"' . str_repeat('x', 10000) . '"}';
        $message = "v1:errors:{$data}";
        $frame = strlen($message) . ":{$message}";

        $payload->append($frame);

        $this->assertTrue($payload->isComplete());
        $this->assertTrue($payload->isValid());
        $this->assertSame($data, $payload->data());
    }

    #[Test]
    public function it_handles_large_payloads_in_small_chunks(): void
    {
        $payload = new Payload();

        $data = '{"data":"' . str_repeat('x', 10000) . '"}';
        $message = "v1:errors:{$data}";
        $frame = strlen($message) . ":{$message}";

        $chunkSize = 100;
        $offset = 0;

        while ($offset < strlen($frame)) {
            $chunk = substr($frame, $offset, $chunkSize);
            $payload->append($chunk);
            $offset += $chunkSize;
        }

        $this->assertTrue($payload->isComplete());
        $this->assertTrue($payload->isValid());
        $this->assertSame('errors', $payload->type());
        $this->assertSame($data, $payload->data());
    }

    #[Test]
    public function it_returns_null_for_incomplete_payload_accessors(): void
    {
        $payload = new Payload();

        $this->assertFalse($payload->isComplete());
        $this->assertFalse($payload->isValid());
        $this->assertNull($payload->version());
        $this->assertNull($payload->type());
        $this->assertNull($payload->data());
        $this->assertNull($payload->baseType());
        $this->assertFalse($payload->isTest());
    }

    #[Test]
    public function it_handles_payload_with_colons_in_json_data(): void
    {
        $payload = new Payload();

        $data = '{"url":"https://example.com:8080","time":"12:30:00"}';
        $message = "v1:logs:{$data}";
        $frame = strlen($message) . ":{$message}";

        $payload->append($frame);

        $this->assertTrue($payload->isComplete());
        $this->assertTrue($payload->isValid());
        $this->assertSame('logs', $payload->type());
        $this->assertSame($data, $payload->data());
    }

    #[Test]
    public function it_ignores_appends_after_completion(): void
    {
        $payload = new Payload();

        $data = '{"error":"test"}';
        $message = "v1:errors:{$data}";
        $frame = strlen($message) . ":{$message}";

        $payload->append($frame);
        $this->assertTrue($payload->isComplete());

        $payload->append('extra garbage data');
        $this->assertSame($data, $payload->data());
        $this->assertSame('errors', $payload->type());
    }

    #[Test]
    public function it_resets_completely(): void
    {
        $payload = new Payload();

        $data = '{"error":"test"}';
        $message = "v1:errors:{$data}";
        $frame = strlen($message) . ":{$message}";

        $payload->append($frame);
        $this->assertTrue($payload->isComplete());

        $payload->reset();

        $this->assertFalse($payload->isComplete());
        $this->assertFalse($payload->isValid());
        $this->assertNull($payload->version());
        $this->assertNull($payload->type());
        $this->assertNull($payload->data());
    }

    #[Test]
    public function it_parses_three_sequential_payloads(): void
    {
        $types = ['errors', 'traces', 'logs'];
        $frames = '';

        foreach ($types as $type) {
            $data = "{\"type\":\"{$type}\"}";
            $message = "v1:{$type}:{$data}";
            $frames .= strlen($message) . ":{$message}";
        }

        $payload = new Payload();
        $payload->append($frames);

        foreach ($types as $i => $type) {
            $this->assertTrue($payload->isComplete(), "Payload {$i} should be complete");
            $this->assertTrue($payload->isValid(), "Payload {$i} should be valid");
            $this->assertSame($type, $payload->type(), "Payload {$i} type mismatch");
            $this->assertSame("{\"type\":\"{$type}\"}", $payload->data(), "Payload {$i} data mismatch");

            if ($i < count($types) - 1) {
                $payload->reset();
            }
        }
    }

    #[Test]
    public function it_handles_empty_json_data(): void
    {
        $payload = new Payload();

        $data = '{}';
        $message = "v1:errors:{$data}";
        $frame = strlen($message) . ":{$message}";

        $payload->append($frame);

        $this->assertTrue($payload->isComplete());
        $this->assertTrue($payload->isValid());
        $this->assertSame('{}', $payload->data());
    }

    #[Test]
    public function it_handles_single_byte_chunks(): void
    {
        $payload = new Payload();

        $data = '{"a":1}';
        $message = "v1:errors:{$data}";
        $frame = strlen($message) . ":{$message}";

        for ($i = 0; $i < strlen($frame); $i++) {
            $payload->append($frame[$i]);
        }

        $this->assertTrue($payload->isComplete());
        $this->assertTrue($payload->isValid());
        $this->assertSame('errors', $payload->type());
        $this->assertSame($data, $payload->data());
    }
}
