<?php

namespace Spatie\FlareDaemon;

use React\Stream\WritableResourceStream;
use Spatie\FlareDaemon\Contracts\LoopContract;

class OutputWriter
{
    private ?WritableResourceStream $stream = null;

    public function __construct(
        private LoopContract $loop,
    ) {
    }

    public function write(string $message): void
    {
        if ($this->loop->running()) {
            $this->getStream()->write($message);

            return;
        }

        fwrite(STDOUT, $message);
    }

    public function writeLn(string $message): void
    {
        $this->write($message.PHP_EOL);
    }

    private function getStream(): WritableResourceStream
    {
        if ($this->stream === null) {
            $this->stream = new WritableResourceStream(STDOUT, $this->loop->get());
        }

        return $this->stream;
    }
}
