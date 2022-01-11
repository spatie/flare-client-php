<?php

use Spatie\FlareClient\Context\ConsoleContextProvider;

it('can return the console context as an array', function () {
    $arguments = [
        'argument 1',
        'argument 2',
        'argument 3',
    ];

    $context = new ConsoleContextProvider($arguments);

    expect($context->toArray())->toEqual(['arguments' => $arguments]);
});
