<?php

namespace Spatie\FlareClient\Support;

use Spatie\FlareClient\Spans\Span;

class GracefulSpanEnder
{
    public function shouldGracefullyEndSpan(Span $span): bool
    {
        return true;
    }
}
