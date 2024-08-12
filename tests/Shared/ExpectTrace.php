<?php

namespace Spatie\FlareClient\Tests\Shared;

use Closure;
use Exception;

class ExpectTrace
{
    protected int $spanAssertCounter = 0;

    public function __construct(
        public array $trace
    ) {
    }

    public function hasSpanCount(int $count): self
    {
        expect($this->trace)->toHaveCount($count);

        return $this;
    }

    /**
     * @param Closure(ExpectSpan): void $closure
     */
    public function span(
        Closure $closure,
        ?Span &$span = null,
    ): self
    {
        $span = array_values($this->trace)[$this->spanAssertCounter] ?? null;

        if($span === null){
            throw new Exception('Span is not recorded');
        }

        $closure(new ExpectSpan($span));

        $this->spanAssertCounter++;

        return $this;
    }
}
