<?php

namespace Spatie\FlareClient\Tests\Context;

use Spatie\FlareClient\Context\ConsoleContext;
use Spatie\FlareClient\Tests\TestCase;

class ConsoleContextTest extends TestCase
{
    /** @test */
    public function it_can_return_the_context_as_an_array()
    {
        $arguments = [
            'argument 1',
            'argument 2',
            'argument 3',
        ];

        $context = new ConsoleContext($arguments);

        $this->assertEquals(['arguments' => $arguments], $context->toArray());
    }
}
