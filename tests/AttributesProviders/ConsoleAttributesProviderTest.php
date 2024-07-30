<?php


use Spatie\FlareClient\AttributesProviders\ConsoleAttributesProvider;

it('can return the console context as an array', function () {
    $arguments = [
        'argument 1',
        'argument 2',
        'argument 3',
    ];

    $context = new ConsoleAttributesProvider();

    expect($context->toArray($arguments))->toEqual(['process.command_args' => $arguments]);
});
