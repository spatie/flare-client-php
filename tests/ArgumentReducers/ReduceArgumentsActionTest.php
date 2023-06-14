<?php

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use Spatie\Backtrace\Frame;
use Spatie\FlareClient\Arguments\ArgumentReducers;
use Spatie\FlareClient\Arguments\ProvidedArgument;
use Spatie\FlareClient\Arguments\ReduceArgumentsAction;
use Spatie\FlareClient\Tests\TestClasses\FakeIntBackedEnum;
use Spatie\FlareClient\Tests\TestClasses\FakeStringBackedEnum;
use Spatie\FlareClient\Tests\TestClasses\FakeUnitEnum;
use Spatie\FlareClient\Tests\TestClasses\TraceArguments;
use Symfony\Component\HttpFoundation\Request;

it('can reduce frames with arguments', function (
    Frame $frame,
    array $expected,
) {
    $reduced = (new ReduceArgumentsAction(ArgumentReducers::default()))->execute($frame);

    expect($reduced)->toEqual($expected);
})->with(fn () => [
    yield 'without arguments enabled in trace' => [
        TraceArguments::create()->withoutArgumentsEnabledInTrace(),
        [],
    ],
    yield 'without arguments' => [TraceArguments::create()->withoutArguments(), []],
    yield 'simple arguments' => [
        TraceArguments::create()->withSimpleArguments(
            true: true,
            false: false,
            emptyString: '',
            string: 'Hello World',
            int: 42,
            intMax: PHP_INT_MAX,
            float: 3.14,
            floatNan: 10,
            floatInfinity: INF,
            null: null,
        ), [
            (new ProvidedArgument('true', reducedValue: true))->toArray(),
            (new ProvidedArgument('false', reducedValue: false))->toArray(),
            (new ProvidedArgument('emptyString', reducedValue: ''))->toArray(),
            (new ProvidedArgument('string', reducedValue: 'Hello World'))->toArray(),
            (new ProvidedArgument('int', reducedValue: 42))->toArray(),
            (new ProvidedArgument('intMax', reducedValue: PHP_INT_MAX))->toArray(),
            (new ProvidedArgument('float', reducedValue: 3.14))->toArray(),
            (new ProvidedArgument('floatNan', reducedValue: 10))->toArray(),
            (new ProvidedArgument('floatInfinity', reducedValue: INF))->toArray(),
            (new ProvidedArgument('null', reducedValue: null))->toArray(),
        ],
    ],
    yield 'with array of simple values' => [
        TraceArguments::create()->withArray(
            ['a', 'b', 'c']
        ), [
            (new ProvidedArgument('array', reducedValue: ['a', 'b', 'c']))->toArray(),
        ],
    ],
    yield 'with array of complex values' => [
        TraceArguments::create()->withArray(
            [
                new DateTimeZone('Europe/Brussels'),
                new DateTimeZone('Europe/Amsterdam'),
            ]
        ), [
            (new ProvidedArgument('array', reducedValue: [
                'object (DateTimeZone)',
                'object (DateTimeZone)',
            ]))->toArray(),
        ],
    ],
    yield 'with array which gets truncated' => [
        TraceArguments::create()->withArray(
            array_fill(0, 100, 'a')
        ), [
            (new ProvidedArgument('array', truncated: true, reducedValue: array_fill(0, 25, 'a')))->toArray(),
        ],
    ],
    yield 'with array of sub arrays which get reduced simply' => [
        TraceArguments::create()->withArray(
            [
                'string',
                new DateTimeZone('Europe/Brussels'),
                [
                    'string',
                    new DateTimeZone('Europe/Brussels'),
                    ['a', 'b', 'c'],
                ],
            ]
        ), [
            (new ProvidedArgument('array', reducedValue: [
                'string',
                'object (DateTimeZone)',
                'array (size=3)',
            ]))->toArray(),
        ],
    ],
    yield 'with defaults' => [
        TraceArguments::create()->withDefaults(
            stringA: 'A',
        ), [
            (new ProvidedArgument('stringA', reducedValue: 'A'))->toArray(),
            (new ProvidedArgument('stringB', hasDefaultValue: true, defaultValue: 'B', defaultValueUsed: true))->toArray(),
        ],
    ],
    yield 'with defaults and provided other value then default' => [
        TraceArguments::create()->withDefaults(
            stringA: 'A',
            stringB: 'notB'
        ), [
            (new ProvidedArgument('stringA', reducedValue: 'A'))->toArray(),
            (new ProvidedArgument('stringB', hasDefaultValue: true, defaultValue: 'B', defaultValueUsed: false, reducedValue: 'notB'))->toArray(),
        ],
    ],
    yield 'with variadic argument (not provided)' => [
        TraceArguments::create()->withVariadicArgument('base'),
        [
            (new ProvidedArgument('base', reducedValue: 'base'))->toArray(),
            (new ProvidedArgument('strings', isVariadic: true, defaultValue: [], defaultValueUsed: true, reducedValue: null))->toArray(),
        ],
    ],
    yield 'with variadic argument (one provided)' => [
        TraceArguments::create()->withVariadicArgument('base', 'string'),
        [
            (new ProvidedArgument('base', reducedValue: 'base'))->toArray(),
            (new ProvidedArgument('strings', isVariadic: true, defaultValue: [], defaultValueUsed: false, reducedValue: ['string']))->toArray(),
        ],
    ],
    yield 'with variadic argument (multiple provided)' => [
        TraceArguments::create()->withVariadicArgument('base', 'string', 'another', 'one'),
        [
            (new ProvidedArgument('base', reducedValue: 'base'))->toArray(),
            (new ProvidedArgument('strings', isVariadic: true, defaultValue: [], defaultValueUsed: false, reducedValue: ['string', 'another', 'one']))->toArray(),
        ],
    ],
    yield 'with default + variadic argument (default + variadic not provided)' => [
        TraceArguments::create()->withDefaultAndVardiadicArgument(),
        [
            (new ProvidedArgument('base', hasDefaultValue: true, defaultValue: 'base', defaultValueUsed: true))->toArray(),
            (new ProvidedArgument('strings', isVariadic: true, defaultValue: [], defaultValueUsed: true, reducedValue: null))->toArray(),
        ],
    ],
    yield 'with default + variadic argument (variadic not provided)' => [
        TraceArguments::create()->withDefaultAndVardiadicArgument('base'),
        [
            (new ProvidedArgument('base', hasDefaultValue: true, defaultValue: 'base', defaultValueUsed: false, reducedValue: 'base'))->toArray(),
            (new ProvidedArgument('strings', isVariadic: true, defaultValue: [], defaultValueUsed: true, reducedValue: null))->toArray(),
        ],
    ],
    yield 'with default + variadic argument (one provided)' => [
        TraceArguments::create()->withDefaultAndVardiadicArgument('base', 'string'),
        [
            (new ProvidedArgument('base', hasDefaultValue: true, defaultValue: 'base', defaultValueUsed: false, reducedValue: 'base'))->toArray(),
            (new ProvidedArgument('strings', isVariadic: true, defaultValue: [], defaultValueUsed: false, reducedValue: ['string']))->toArray(),
        ],
    ],
    yield 'with default + variadic argument (multiple provided)' => [
        TraceArguments::create()->withDefaultAndVardiadicArgument('base', 'string', 'another', 'one'),
        [
            (new ProvidedArgument('base', hasDefaultValue: true, defaultValue: 'base', defaultValueUsed: false, reducedValue: 'base'))->toArray(),
            (new ProvidedArgument('strings', isVariadic: true, defaultValue: [], defaultValueUsed: false, reducedValue: ['string', 'another', 'one']))->toArray(),
        ],
    ],
    yield 'with closure' => [
        TraceArguments::create()->withClosure(
            fn () => 'Hello World'
        ), [
            (new ProvidedArgument('closure', reducedValue: '{closure}('.__FILE__.':'.__LINE__ - 2 .'-'.__LINE__ - 1 .')'))->toArray(),
        ],
    ],
    yield 'with date' => [
        TraceArguments::create()->withDate(
            new DateTime('2020-05-16 14:00:00', new DateTimeZone('Europe/Brussels')),
            new DateTimeImmutable('2020-05-16 14:00:00', new DateTimeZone('Europe/Brussels')),
            new Carbon('2020-05-16 14:00:00', new DateTimeZone('Europe/Brussels')),
            new CarbonImmutable('2020-05-16 14:00:00', new DateTimeZone('Europe/Brussels')),
        ), [
            (new ProvidedArgument('dateTime', reducedValue: '16 May 2020 14:00:00 +02:00 (DateTime)'))->toArray(),
            (new ProvidedArgument('dateTimeImmutable', reducedValue: '16 May 2020 14:00:00 +02:00 (DateTimeImmutable)'))->toArray(),
            (new ProvidedArgument('carbon', reducedValue: '16 May 2020 14:00:00 +02:00 (Carbon\Carbon)'))->toArray(),
            (new ProvidedArgument('carbonImmutable', reducedValue: '16 May 2020 14:00:00 +02:00 (Carbon\CarbonImmutable)'))->toArray(),
        ],
    ],
    yield 'with timezone' => [
        TraceArguments::create()->withTimeZone(
            new DateTimeZone('Europe/Brussels'),
            new CarbonTimeZone('Europe/Brussels'),
        ), [
            (new ProvidedArgument('dateTimeZone', reducedValue: 'Europe/Brussels (DateTimeZone)'))->toArray(),
            (new ProvidedArgument('carbonTimeZone', reducedValue: 'Europe/Brussels (Carbon\CarbonTimeZone)'))->toArray(),
        ],
    ],
    yield 'with Symfony Request' => [
        TraceArguments::create()->withSymfonyRequest(
            Request::create('https://spatie.be/flare'),
        ), [
            (new ProvidedArgument('request', reducedValue: 'GET|https://spatie.be/flare (Symfony\Component\HttpFoundation\Request)'))->toArray(),
        ],
    ],
    yield 'with sensitive parameter' => [
        TraceArguments::create()->withSensitiveParameter('secret'),
        [
            (new ProvidedArgument(
                'sensitive',
                reducedValue: version_compare(PHP_VERSION, '8.2', '>=')
                    ? 'object (SensitiveParameterValue)'
                    : 'secret'
            ))->toArray(),
        ],
    ],
]);

it('will reduce values with enums', function () {
    $frame = TraceArguments::create()->withEnums(
        unitEnum: FakeUnitEnum::A,
        stringBackedEnum: FakeStringBackedEnum::A,
        intBackedEnum: FakeIntBackedEnum::A,
    );

    $reduced = (new ReduceArgumentsAction(ArgumentReducers::default()))->execute($frame);

    expect($reduced)->toEqual([
        (new ProvidedArgument('unitEnum', reducedValue: FakeUnitEnum::class.'::A'))->toArray(),
        (new ProvidedArgument('stringBackedEnum', reducedValue: FakeStringBackedEnum::class.'::A'))->toArray(),
        (new ProvidedArgument('intBackedEnum', reducedValue: FakeIntBackedEnum::class.'::A'))->toArray(),
    ]);
})->skip(version_compare(PHP_VERSION, '8.1', '<'), 'PHP too old');

it('will reduce values even when no reducers are specified', function () {
    $frame = TraceArguments::create()->withCombination(
        'string',
        new DateTimeZone('Europe/Brussels'),
        42,
        69
    );

    $reduced = (new ReduceArgumentsAction(ArgumentReducers::create([])))->execute($frame);

    expect($reduced)->toEqual([
        (new ProvidedArgument('simple', reducedValue: 'string'))->toArray(),
        (new ProvidedArgument('object', reducedValue: 'object (DateTimeZone)'))->toArray(),
        (new ProvidedArgument('variadic', reducedValue: [42, 69], isVariadic: true))->toArray(),
    ]);
});

it('will transform an ProvidedArgument to array', function (
    ProvidedArgument $argument,
    array $expected
) {
    expect($argument->toArray())->toEqual($expected);
})->with(fn () => [
    yield 'base' => [
        new ProvidedArgument('string', reducedValue: 'string'),
        [
            'name' => 'string',
            'value' => 'string',
            'passed_by_reference' => false,
            'is_variadic' => false,
            'truncated' => false,
        ],
    ],
    yield 'base passed by reference' => [
        new ProvidedArgument('string', passedByReference: true, reducedValue: 'string'),
        [
            'name' => 'string',
            'value' => 'string',
            'passed_by_reference' => true,
            'is_variadic' => false,
            'truncated' => false,
        ],
    ],
    yield 'base variadic' => [
        new ProvidedArgument('string', isVariadic: true, reducedValue: 'string'),
        [
            'name' => 'string',
            'value' => 'string',
            'passed_by_reference' => false,
            'is_variadic' => true,
            'truncated' => false,
        ],
    ],
    yield 'base truncated' => [
        new ProvidedArgument('string', truncated: true, reducedValue: 'string'),
        [
            'name' => 'string',
            'value' => 'string',
            'passed_by_reference' => false,
            'is_variadic' => false,
            'truncated' => true,
        ],
    ],
    yield 'default' => [
        new ProvidedArgument('string', hasDefaultValue: true, defaultValue: 'string', defaultValueUsed: true),
        [
            'name' => 'string',
            'value' => 'string',
            'passed_by_reference' => false,
            'is_variadic' => false,
            'truncated' => false,
        ],
    ],
]);
