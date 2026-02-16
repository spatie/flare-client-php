<?php

namespace Spatie\FlareClient\Tests\stubs;

class SubDependencyClass
{
    public function __construct(
        public SomeClass $someClass,
    ) {
    }
}
