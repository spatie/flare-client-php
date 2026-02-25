<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Spatie\FlareDaemon\Server;
use Tests\Connection;
use Tests\LoopFake;
use Tests\OutputWriterFake;

class ServerTest extends TestCase
{
    private Server $server;

    private LoopFake $loop;

    private OutputWriterFake $output;

    /** @var array<int, array{type: string, data: string}> */
    private array $receivedPayloads = [];

    /** @var array<int, array{baseType: string, data: string, connection: Connection}> */
    private array $receivedTestPayloads = [];

    private int $statusCallCount = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loop = new LoopFake();
        $this->output = new OutputWriterFake();

        $this->receivedPayloads = [];
        $this->receivedTestPayloads = [];
        $this->statusCallCount = 0;

        $this->server = new Server(
            $this->loop,
            $this->output,
            function (string $type, string $data): void {
                $this->receivedPayloads[] = ['type' => $type, 'data' => $data];
            },
            function (string $baseType, string $data, $connection): void {
                /** @var Connection $connection */
                $this->receivedTestPayloads[] = [
                    'baseType' => $baseType,
                    'data' => $data,
                    'connection' => $connection,
                ];
            },
            function (): array {
                $this->statusCallCount++;

                return [
                    'buffers' => ['errors' => 0, 'traces' => 0, 'logs' => 0],
                    'in_flight' => 0,
                ];
            },
        );
    }

    /**
     * Simulate a new connection being accepted by the server.
     */
    private function simulateConnection(): Connection
    {
        $connection = new Connection();

        $reflection = new \ReflectionMethod($this->server, 'handleConnection');
        $reflection->invoke($this->server, $connection);

        return $connection;
    }

    #[Test]
    public function it_handles_a_single_payload(): void
    {
        $connection = $this->simulateConnection();

        $data = '{"error":"test"}';
        $connection->sendPayload('errors', $data);

        $this->assertCount(1, $this->receivedPayloads);
        $this->assertSame('errors', $this->receivedPayloads[0]['type']);
        $this->assertSame($data, $this->receivedPayloads[0]['data']);

        $connection->assertWritten('2:OK');
        $connection->assertOpen();
    }

    #[Test]
    public function it_handles_multiple_payloads_on_persistent_connection(): void
    {
        $connection = $this->simulateConnection();

        $data1 = '{"error":"first"}';
        $connection->sendPayload('errors', $data1);

        $data2 = '{"trace":"second"}';
        $connection->sendPayload('traces', $data2);

        $data3 = '{"log":"third"}';
        $connection->sendPayload('logs', $data3);

        $this->assertCount(3, $this->receivedPayloads);
        $this->assertSame('errors', $this->receivedPayloads[0]['type']);
        $this->assertSame($data1, $this->receivedPayloads[0]['data']);
        $this->assertSame('traces', $this->receivedPayloads[1]['type']);
        $this->assertSame($data2, $this->receivedPayloads[1]['data']);
        $this->assertSame('logs', $this->receivedPayloads[2]['type']);
        $this->assertSame($data3, $this->receivedPayloads[2]['data']);

        $connection->assertWrittenCount(3);
        $connection->assertOpen();
    }

    #[Test]
    public function it_responds_to_ping_and_keeps_connection_open(): void
    {
        $connection = $this->simulateConnection();

        $connection->receive('PING');

        $connection->assertWritten('2:OK');
        $connection->assertWrittenCount(1);
        $connection->assertOpen();
    }

    #[Test]
    public function it_handles_ping_followed_by_payload_on_same_connection(): void
    {
        $connection = $this->simulateConnection();

        $connection->receive('PING');

        $data = '{"error":"after-ping"}';
        $connection->sendPayload('errors', $data);

        $connection->assertWrittenCount(2);
        $this->assertCount(1, $this->receivedPayloads);
        $this->assertSame($data, $this->receivedPayloads[0]['data']);
        $connection->assertOpen();
    }

    #[Test]
    public function it_responds_to_status_with_json_payload(): void
    {
        $connection = $this->simulateConnection();

        $connection->receive('STATUS');

        $this->assertSame(1, $this->statusCallCount);

        $expectedJson = '{"buffers":{"errors":0,"traces":0,"logs":0},"in_flight":0}';
        $expectedLength = strlen($expectedJson);
        $connection->assertLastWritten("{$expectedLength}:{$expectedJson}");
        $connection->assertOpen();
    }

    #[Test]
    public function it_handles_status_followed_by_payload_on_same_connection(): void
    {
        $connection = $this->simulateConnection();

        $connection->receive('STATUS');

        $data = '{"error":"after-status"}';
        $connection->sendPayload('errors', $data);

        $connection->assertWrittenCount(2);
        $this->assertSame(1, $this->statusCallCount);
        $this->assertCount(1, $this->receivedPayloads);
        $connection->assertOpen();
    }

    #[Test]
    public function it_rejects_invalid_version(): void
    {
        $connection = $this->simulateConnection();

        $message = 'v2:errors:{"error":"test"}';
        $frame = strlen($message) . ":{$message}";
        $connection->receive($frame);

        $this->assertCount(0, $this->receivedPayloads);
        $connection->assertWritten('2:OK');
        $connection->assertOpen();
    }

    #[Test]
    public function it_logs_invalid_version(): void
    {
        $connection = $this->simulateConnection();

        $message = 'v2:errors:{"error":"test"}';
        $frame = strlen($message) . ":{$message}";
        $connection->receive($frame);

        $this->assertTrue($this->output->hasMessage("Invalid payload: unsupported version 'v2'"));
    }

    #[Test]
    public function it_accepts_valid_payload_after_invalid_version_rejection(): void
    {
        $connection = $this->simulateConnection();

        // Send invalid version
        $invalidMessage = 'v2:errors:{"error":"invalid"}';
        $invalidFrame = strlen($invalidMessage) . ":{$invalidMessage}";
        $connection->receive($invalidFrame);

        $this->assertCount(0, $this->receivedPayloads);

        // Send valid payload on same connection
        $data = '{"error":"valid"}';
        $connection->sendPayload('errors', $data);

        $this->assertCount(1, $this->receivedPayloads);
        $this->assertSame($data, $this->receivedPayloads[0]['data']);
        $connection->assertOpen();
    }

    #[Test]
    public function it_handles_chunked_payload_arrival(): void
    {
        $connection = $this->simulateConnection();

        $data = '{"error":"chunked-test"}';
        $message = "v1:errors:{$data}";
        $frame = strlen($message) . ":{$message}";

        // Split frame into chunks
        $chunk1 = substr($frame, 0, 10);
        $chunk2 = substr($frame, 10, 10);
        $chunk3 = substr($frame, 20);

        $connection->receive($chunk1);
        $this->assertCount(0, $this->receivedPayloads);

        $connection->receive($chunk2);
        $this->assertCount(0, $this->receivedPayloads);

        $connection->receive($chunk3);
        $this->assertCount(1, $this->receivedPayloads);
        $this->assertSame($data, $this->receivedPayloads[0]['data']);
        $connection->assertWritten('2:OK');
        $connection->assertOpen();
    }

    #[Test]
    public function it_handles_multiple_payloads_in_single_chunk(): void
    {
        $connection = $this->simulateConnection();

        $data1 = '{"error":"first"}';
        $message1 = "v1:errors:{$data1}";
        $frame1 = strlen($message1) . ":{$message1}";

        $data2 = '{"trace":"second"}';
        $message2 = "v1:traces:{$data2}";
        $frame2 = strlen($message2) . ":{$message2}";

        // Send both frames concatenated in a single chunk
        $connection->receive($frame1 . $frame2);

        $this->assertCount(2, $this->receivedPayloads);
        $this->assertSame('errors', $this->receivedPayloads[0]['type']);
        $this->assertSame($data1, $this->receivedPayloads[0]['data']);
        $this->assertSame('traces', $this->receivedPayloads[1]['type']);
        $this->assertSame($data2, $this->receivedPayloads[1]['data']);

        $connection->assertWrittenCount(2);
        $connection->assertOpen();
    }

    #[Test]
    public function it_handles_test_payload(): void
    {
        $connection = $this->simulateConnection();

        $data = '{"test":true}';
        $connection->sendPayload('errors_test', $data);

        $this->assertCount(0, $this->receivedPayloads);
        $this->assertCount(1, $this->receivedTestPayloads);
        $this->assertSame('errors', $this->receivedTestPayloads[0]['baseType']);
        $this->assertSame($data, $this->receivedTestPayloads[0]['data']);
        $this->assertSame($connection, $this->receivedTestPayloads[0]['connection']);

        $connection->assertWritten('2:OK');
        $connection->assertOpen();
    }

    #[Test]
    public function it_handles_test_payloads_for_all_types(): void
    {
        $connection = $this->simulateConnection();

        $types = ['errors', 'traces', 'logs'];

        foreach ($types as $type) {
            $data = "{\"type\":\"{$type}\"}";
            $connection->sendPayload("{$type}_test", $data);
        }

        $this->assertCount(0, $this->receivedPayloads);
        $this->assertCount(3, $this->receivedTestPayloads);

        foreach ($types as $i => $type) {
            $this->assertSame($type, $this->receivedTestPayloads[$i]['baseType']);
        }

        $connection->assertOpen();
    }

    #[Test]
    public function it_handles_connection_close_gracefully(): void
    {
        $connection = $this->simulateConnection();

        $data = '{"error":"before-close"}';
        $connection->sendPayload('errors', $data);

        $connection->close();

        $this->assertCount(1, $this->receivedPayloads);
        $connection->assertClosed();
    }

    #[Test]
    public function it_handles_multiple_independent_connections(): void
    {
        $connection1 = $this->simulateConnection();
        $connection2 = $this->simulateConnection();

        $data1 = '{"error":"conn1"}';
        $connection1->sendPayload('errors', $data1);

        $data2 = '{"error":"conn2"}';
        $connection2->sendPayload('errors', $data2);

        $this->assertCount(2, $this->receivedPayloads);
        $this->assertSame($data1, $this->receivedPayloads[0]['data']);
        $this->assertSame($data2, $this->receivedPayloads[1]['data']);

        $connection1->assertWrittenCount(1);
        $connection2->assertWrittenCount(1);
        $connection1->assertOpen();
        $connection2->assertOpen();
    }

    #[Test]
    public function it_handles_mixed_commands_and_payloads_on_same_connection(): void
    {
        $connection = $this->simulateConnection();

        $connection->receive('PING');
        $connection->assertWrittenCount(1);

        $data = '{"error":"after-ping"}';
        $connection->sendPayload('errors', $data);
        $connection->assertWrittenCount(2);

        $connection->receive('STATUS');
        $connection->assertWrittenCount(3);

        $data2 = '{"trace":"after-status"}';
        $connection->sendPayload('traces', $data2);
        $connection->assertWrittenCount(4);

        $this->assertCount(2, $this->receivedPayloads);
        $this->assertSame(1, $this->statusCallCount);
        $connection->assertOpen();
    }

    #[Test]
    public function it_handles_payload_with_colons_in_data(): void
    {
        $connection = $this->simulateConnection();

        $data = '{"url":"https://example.com:8080","time":"12:30:00"}';
        $connection->sendPayload('logs', $data);

        $this->assertCount(1, $this->receivedPayloads);
        $this->assertSame('logs', $this->receivedPayloads[0]['type']);
        $this->assertSame($data, $this->receivedPayloads[0]['data']);
        $connection->assertOpen();
    }

    #[Test]
    public function it_handles_large_payload(): void
    {
        $connection = $this->simulateConnection();

        $data = '{"data":"' . str_repeat('x', 100000) . '"}';
        $connection->sendPayload('errors', $data);

        $this->assertCount(1, $this->receivedPayloads);
        $this->assertSame($data, $this->receivedPayloads[0]['data']);
        $connection->assertWritten('2:OK');
        $connection->assertOpen();
    }

    #[Test]
    public function it_handles_large_payload_in_chunks(): void
    {
        $connection = $this->simulateConnection();

        $data = '{"data":"' . str_repeat('x', 50000) . '"}';
        $message = "v1:errors:{$data}";
        $frame = strlen($message) . ":{$message}";

        $chunkSize = 1024;
        $offset = 0;

        while ($offset < strlen($frame)) {
            $chunk = substr($frame, $offset, $chunkSize);
            $connection->receive($chunk);
            $offset += $chunkSize;
        }

        $this->assertCount(1, $this->receivedPayloads);
        $this->assertSame($data, $this->receivedPayloads[0]['data']);
        $connection->assertWritten('2:OK');
        $connection->assertOpen();
    }

    #[Test]
    public function it_handles_ping_with_trailing_whitespace(): void
    {
        $connection = $this->simulateConnection();

        $connection->receive("PING\n");

        $connection->assertWritten('2:OK');
        $connection->assertOpen();
    }

    #[Test]
    public function it_handles_status_with_trailing_whitespace(): void
    {
        $connection = $this->simulateConnection();

        $connection->receive("STATUS\n");

        $this->assertSame(1, $this->statusCallCount);
        $connection->assertOpen();
    }

    #[Test]
    public function server_close_nullifies_server_reference(): void
    {
        $this->server->close();

        $reflection = new \ReflectionProperty($this->server, 'server');
        $this->assertNull($reflection->getValue($this->server));
    }
}
