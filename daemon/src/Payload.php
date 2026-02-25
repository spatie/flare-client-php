<?php

namespace Spatie\FlareDaemon;

class Payload
{
    private string $buffer = '';

    private ?int $expectedLength = null;

    private ?string $version = null;

    private ?string $type = null;

    private ?string $data = null;

    private bool $complete = false;

    private string $overflow = '';

    public function append(string $chunk): void
    {
        if ($this->complete) {
            return;
        }

        $this->buffer .= $chunk;

        if ($this->expectedLength === null) {
            $this->parseHeader();
        }

        if ($this->expectedLength === null) {
            return;
        }

        $messageStart = strpos($this->buffer, ':');

        if ($messageStart === false) {
            return;
        }

        $messageBytes = substr($this->buffer, $messageStart + 1);
        $currentLength = strlen($messageBytes);

        if ($currentLength < $this->expectedLength) {
            return;
        }

        $message = substr($messageBytes, 0, $this->expectedLength);

        if ($currentLength > $this->expectedLength) {
            $this->overflow = substr($messageBytes, $this->expectedLength);
        }

        $this->parseMessage($message);
    }

    private function parseHeader(): void
    {
        $colonPos = strpos($this->buffer, ':');

        if ($colonPos === false) {
            return;
        }

        $lengthStr = substr($this->buffer, 0, $colonPos);

        if (! ctype_digit($lengthStr)) {
            return;
        }

        $this->expectedLength = (int) $lengthStr;
    }

    private function parseMessage(string $message): void
    {
        $colonPos = strpos($message, ':');

        if ($colonPos === false) {
            return;
        }

        $version = substr($message, 0, $colonPos);

        if ($version !== 'v1') {
            $this->complete = true;
            $this->version = $version;

            return;
        }

        $this->version = $version;

        $rest = substr($message, $colonPos + 1);
        $typeEnd = strpos($rest, ':');

        if ($typeEnd === false) {
            return;
        }

        $this->type = substr($rest, 0, $typeEnd);
        $this->data = substr($rest, $typeEnd + 1);
        $this->complete = true;
    }

    public function isComplete(): bool
    {
        return $this->complete;
    }

    public function isValid(): bool
    {
        return $this->complete && $this->version === 'v1' && $this->type !== null && $this->data !== null;
    }

    public function version(): ?string
    {
        return $this->version;
    }

    public function type(): ?string
    {
        return $this->type;
    }

    public function data(): ?string
    {
        return $this->data;
    }

    public function isTest(): bool
    {
        if ($this->type === null) {
            return false;
        }

        return str_ends_with($this->type, '_test');
    }

    public function baseType(): ?string
    {
        if ($this->type === null) {
            return null;
        }

        if ($this->isTest()) {
            return substr($this->type, 0, -5);
        }

        return $this->type;
    }

    public function overflow(): string
    {
        return $this->overflow;
    }

    public function reset(): void
    {
        $overflow = $this->overflow;

        $this->buffer = '';
        $this->expectedLength = null;
        $this->version = null;
        $this->type = null;
        $this->data = null;
        $this->complete = false;
        $this->overflow = '';

        if ($overflow !== '') {
            $this->append($overflow);
        }
    }
}
