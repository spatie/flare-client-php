<?php

namespace Spatie\FlareClient\Contracts;

use Spatie\FlareClient\Spans\Span;

interface SpansRecorder extends Recorder
{
    /** @return array<Span> */
    public function getSpans(): array;
}
