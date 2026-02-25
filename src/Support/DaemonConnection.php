<?php

namespace Spatie\FlareClient\Support;

use Spatie\FlareClient\Senders\Exceptions\ConnectionError;

class DaemonConnection
{
    private static ?self $instance = null;

    /** @var resource|null */
    private $socket = null;

    private function __construct(
        private string $daemonUrl,
    ) {
    }

    public static function create(string $daemonUrl): self
    {
        return self::$instance ??= new self($daemonUrl);
    }

    public function write(string $data): void
    {
        $socket = $this->getSocket();

        $result = @fwrite($socket, $data);

        if ($result === false) {
            $this->close();

            throw new ConnectionError("Failed to write to daemon at {$this->daemonUrl}");
        }
    }

    public function read(): string
    {
        $socket = $this->getSocket();

        $response = @fread($socket, 8192);

        if ($response === false || $response === '') {
            $this->close();

            throw new ConnectionError("Failed to read from daemon at {$this->daemonUrl}");
        }

        return $response;
    }

    public function readWithTimeout(int $seconds): string
    {
        $socket = $this->getSocket();

        stream_set_timeout($socket, $seconds);

        $response = @fread($socket, 65536);

        $info = stream_get_meta_data($socket);

        if ($info['timed_out']) {
            throw new ConnectionError("Timed out waiting for daemon response after {$seconds} seconds");
        }

        if ($response === false || $response === '') {
            $this->close();

            throw new ConnectionError("Failed to read from daemon at {$this->daemonUrl}");
        }

        return $response;
    }

    public function ping(): bool
    {
        try {
            $socket = $this->getSocket();

            $result = @fwrite($socket, 'PING');

            if ($result === false) {
                $this->close();

                return false;
            }

            stream_set_timeout($socket, 5);

            $response = @fread($socket, 8192);

            if ($response === false || $response === '') {
                $this->close();

                return false;
            }

            return $response === '2:OK';
        } catch (ConnectionError) {
            return false;
        }
    }

    /** @return array<string, mixed>|null */
    public function status(): ?array
    {
        try {
            $socket = $this->getSocket();

            $result = @fwrite($socket, 'STATUS');

            if ($result === false) {
                $this->close();

                return null;
            }

            stream_set_timeout($socket, 5);

            $response = @fread($socket, 65536);

            if ($response === false || $response === '') {
                $this->close();

                return null;
            }

            $colonPos = strpos($response, ':');

            if ($colonPos === false) {
                return null;
            }

            $json = substr($response, $colonPos + 1);

            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            return $decoded;
        } catch (ConnectionError) {
            return null;
        }
    }

    public function close(): void
    {
        if ($this->socket !== null) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    public static function reset(): void
    {
        self::$instance?->close();
        self::$instance = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    /** @return resource */
    private function getSocket()
    {
        $this->ensureConnected();

        /** @var resource */
        return $this->socket;
    }

    private function ensureConnected(): void
    {
        if ($this->socket !== null) {
            return;
        }

        $socket = @stream_socket_client(
            "tcp://{$this->daemonUrl}",
            $errorCode,
            $errorMessage,
            5,
        );

        if ($socket === false) {
            throw new ConnectionError("Could not connect to daemon at {$this->daemonUrl}: {$errorMessage} ({$errorCode})");
        }

        $this->socket = $socket;
    }
}
