<?php

namespace Spatie\FlareClient\Tests\stubs;

class DependencyClass
{
    public function __construct(
        public SomeClass $someClass,
        public SomeInterface $someInterface,
    ) {

    }
}
