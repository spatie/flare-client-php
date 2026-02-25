<?php

namespace Tests;

use Spatie\FlareDaemon\OutputWriter;

class OutputWriterFake extends OutputWriter
{
    /** @var array<int, string> */
    private array $messages = [];

    public function __construct()
    {
        // Do not call parent constructor — we don't need a real Loop
    }

    public function write(string $message): void
    {
        $this->messages[] = $message;
    }

    public function writeLn(string $message): void
    {
        $this->messages[] = $message . PHP_EOL;
    }

    /**
     * @return array<int, string>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    public function hasMessage(string $needle): bool
    {
        foreach ($this->messages as $message) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
