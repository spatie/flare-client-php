<?php

use Spatie\FlareClient\Context\ConsoleContextProvider;
use Spatie\FlareClient\Tests\TestCase;


it('can return the context as an array', function () {
    $arguments = [
        'argument 1',
        'argument 2',
        'argument 3',
    ];

    $context = new ConsoleContextProvider($arguments);

    expect($context->toArray())->toEqual(['arguments' => $arguments]);
});
