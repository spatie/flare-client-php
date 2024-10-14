<?php


use Spatie\FlareClient\AttributesProviders\ConsoleAttributesProvider;
use Spatie\FlareClient\Enums\EntryPointType;

it('can return the console context as an array', function () {
    $arguments = [
        'argument 1',
        'argument 2',
        'argument 3',
    ];

    $context = new ConsoleAttributesProvider();

    expect($context->toArray($arguments))->toEqual([
        'process.command_args' => $arguments,
        'flare.entry_point.type' => EntryPointType::Cli,
        'flare.entry_point.value' => 'argument 1 argument 2 argument 3',
        'flare.entry_point.class' => null,
    ]);
});
