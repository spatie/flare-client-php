<?php

use Spatie\FlareClient\AttributesProviders\SymfonyInputCommandAttributesProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

it('flattens a Symfony InputInterface into a list of arguments and options', function () {
    $input = new ArrayInput([
        'command' => 'migrate',
        'name' => 'users',
        '--force' => true,
        '--connection' => 'pgsql',
        '--tags' => ['one', 'two'],
    ]);

    $input->bind(new InputDefinition([
        new InputArgument('command'),
        new InputArgument('name'),
        new InputOption('force', null, InputOption::VALUE_NONE),
        new InputOption('pretend', null, InputOption::VALUE_NONE),
        new InputOption('connection', null, InputOption::VALUE_REQUIRED),
        new InputOption('tags', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY),
        new InputOption('empty', null, InputOption::VALUE_OPTIONAL),
    ]));

    $provider = new SymfonyInputCommandAttributesProvider($input, 'migrate');

    expect($provider->toArray())->toEqual([
        'process.command_args' => [
            'migrate',
            'users',
            '--force',
            '--connection=pgsql',
            '--tags=one,two',
        ],
    ]);
});
