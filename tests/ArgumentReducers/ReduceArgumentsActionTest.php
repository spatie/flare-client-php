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

    expect($reduced)->toEqual(array_map(
        fn (ProvidedArgument $argument) => $argument->toArray(),
        $expected,
    ));
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
            new ProvidedArgument('true', reducedValue: true, originalType: 'bool'),
            new ProvidedArgument('false', reducedValue: false, originalType: 'bool'),
            new ProvidedArgument('emptyString', reducedValue: '', originalType: 'string'),
            new ProvidedArgument('string', reducedValue: 'Hello World', originalType: 'string'),
            new ProvidedArgument('int', reducedValue: 42, originalType: 'int'),
            new ProvidedArgument('intMax', reducedValue: PHP_INT_MAX, originalType: 'int'),
            new ProvidedArgument('float', reducedValue: 3.14, originalType: 'float'),
            new ProvidedArgument('floatNan', reducedValue: 10, originalType: 'float'),
            new ProvidedArgument('floatInfinity', reducedValue: INF, originalType: 'float'),
            new ProvidedArgument('null', reducedValue: null, originalType: 'null'),
        ],
    ],
    yield 'with array of simple values' => [
        TraceArguments::create()->withArray(
            ['a', 'b', 'c']
        ), [
            new ProvidedArgument('array', reducedValue: ['a', 'b', 'c'], originalType: 'array'),
        ],
    ],
    yield 'with array of complex values' => [
        TraceArguments::create()->withArray(
            [
                new DateTimeZone('Europe/Brussels'),
                new DateTimeZone('Europe/Amsterdam'),
            ]
        ), [
            new ProvidedArgument('array', reducedValue: [
                'object (DateTimeZone)',
                'object (DateTimeZone)',
            ], originalType: 'array'),
        ],
    ],
    yield 'with array which gets truncated' => [
        TraceArguments::create()->withArray(
            array_fill(0, 100, 'a')
        ), [
            new ProvidedArgument('array', truncated: true, reducedValue: array_fill(0, 25, 'a'), originalType: 'array'),
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
            new ProvidedArgument('array', reducedValue: [
                'string',
                'object (DateTimeZone)',
                'array (size=3)',
            ], originalType: 'array'),
        ],
    ],
    yield 'with defaults' => [
        TraceArguments::create()->withDefaults(
            stringA: 'A',
        ), [
            new ProvidedArgument('stringA', reducedValue: 'A', originalType: 'string'),
            new ProvidedArgument('stringB', hasDefaultValue: true, defaultValue: 'B', defaultValueUsed: true, originalType: 'string'),
        ],
    ],
    yield 'with defaults and provided other value then default' => [
        TraceArguments::create()->withDefaults(
            stringA: 'A',
            stringB: 'notB'
        ), [
            new ProvidedArgument('stringA', reducedValue: 'A', originalType: 'string'),
            new ProvidedArgument('stringB', hasDefaultValue: true, defaultValue: 'B', defaultValueUsed: false, reducedValue: 'notB', originalType: 'string'),
        ],
    ],
    yield 'with variadic argument (not provided)' => [
        TraceArguments::create()->withVariadicArgument('base'),
        [
            new ProvidedArgument('base', reducedValue: 'base', originalType: 'string'),
            new ProvidedArgument('strings', isVariadic: true, defaultValue: [], defaultValueUsed: true, reducedValue: null, originalType: 'array'),
        ],
    ],
    yield 'with variadic argument (one provided)' => [
        TraceArguments::create()->withVariadicArgument('base', 'string'),
        [
            new ProvidedArgument('base', reducedValue: 'base', originalType: 'string'),
            new ProvidedArgument('strings', isVariadic: true, defaultValue: [], defaultValueUsed: false, reducedValue: ['string'], originalType: 'array'),
        ],
    ],
    yield 'with variadic argument (multiple provided)' => [
        TraceArguments::create()->withVariadicArgument('base', 'string', 'another', 'one'),
        [
            new ProvidedArgument('base', reducedValue: 'base', originalType: 'string'),
            new ProvidedArgument('strings', isVariadic: true, defaultValue: [], defaultValueUsed: false, reducedValue: ['string', 'another', 'one'], originalType: 'array'),
        ],
    ],
    yield 'with default + variadic argument (default + variadic not provided)' => [
        TraceArguments::create()->withDefaultAndVardiadicArgument(),
        [
            new ProvidedArgument('base', hasDefaultValue: true, defaultValue: 'base', defaultValueUsed: true, originalType: 'string'),
            new ProvidedArgument('strings', isVariadic: true, defaultValue: [], defaultValueUsed: true, reducedValue: null, originalType: 'array'),
        ],
    ],
    yield 'with default + variadic argument (variadic not provided)' => [
        TraceArguments::create()->withDefaultAndVardiadicArgument('base'),
        [
            new ProvidedArgument('base', hasDefaultValue: true, defaultValue: 'base', defaultValueUsed: false, reducedValue: 'base', originalType: 'string'),
            new ProvidedArgument('strings', isVariadic: true, defaultValue: [], defaultValueUsed: true, reducedValue: null, originalType: 'array'),
        ],
    ],
    yield 'with default + variadic argument (one provided)' => [
        TraceArguments::create()->withDefaultAndVardiadicArgument('base', 'string'),
        [
            new ProvidedArgument('base', hasDefaultValue: true, defaultValue: 'base', defaultValueUsed: false, reducedValue: 'base', originalType: 'string'),
            new ProvidedArgument('strings', isVariadic: true, defaultValue: [], defaultValueUsed: false, reducedValue: ['string'], originalType: 'array'),
        ],
    ],
    yield 'with default + variadic argument (multiple provided)' => [
        TraceArguments::create()->withDefaultAndVardiadicArgument('base', 'string', 'another', 'one'),
        [
            new ProvidedArgument('base', hasDefaultValue: true, defaultValue: 'base', defaultValueUsed: false, reducedValue: 'base', originalType: 'string'),
            new ProvidedArgument('strings', isVariadic: true, defaultValue: [], defaultValueUsed: false, reducedValue: ['string', 'another', 'one'], originalType: 'array'),
        ],
    ],
    yield 'with closure' => [
        TraceArguments::create()->withClosure(
            fn () => 'Hello World'
        ), [
            new ProvidedArgument('closure', reducedValue: '{closure}('.__FILE__.':'.__LINE__ - 2 .'-'.__LINE__ - 1 .')', originalType: 'Closure'),
        ],
    ],
    yield 'with date' => [
        TraceArguments::create()->withDate(
            new DateTime('2020-05-16 14:00:00', new DateTimeZone('Europe/Brussels')),
            new DateTimeImmutable('2020-05-16 14:00:00', new DateTimeZone('Europe/Brussels')),
            new Carbon('2020-05-16 14:00:00', new DateTimeZone('Europe/Brussels')),
            new CarbonImmutable('2020-05-16 14:00:00', new DateTimeZone('Europe/Brussels')),
        ), [
            new ProvidedArgument('dateTime', reducedValue: '16 May 2020 14:00:00 +02:00 (DateTime)', originalType: DateTime::class),
            new ProvidedArgument('dateTimeImmutable', reducedValue: '16 May 2020 14:00:00 +02:00 (DateTimeImmutable)', originalType: DateTimeImmutable::class),
            new ProvidedArgument('carbon', reducedValue: '16 May 2020 14:00:00 +02:00 (Carbon\Carbon)', originalType: Carbon::class),
            new ProvidedArgument('carbonImmutable', reducedValue: '16 May 2020 14:00:00 +02:00 (Carbon\CarbonImmutable)', originalType: CarbonImmutable::class),
        ],
    ],
    yield 'with timezone' => [
        TraceArguments::create()->withTimeZone(
            new DateTimeZone('Europe/Brussels'),
            new CarbonTimeZone('Europe/Brussels'),
        ), [
            new ProvidedArgument('dateTimeZone', reducedValue: 'Europe/Brussels (DateTimeZone)', originalType: DateTimeZone::class),
            new ProvidedArgument('carbonTimeZone', reducedValue: 'Europe/Brussels (Carbon\CarbonTimeZone)', originalType: CarbonTimeZone::class),
        ],
    ],
    yield 'with Symfony Request' => [
        TraceArguments::create()->withSymfonyRequest(
            Request::create('https://spatie.be/flare'),
        ), [
            new ProvidedArgument('request', reducedValue: 'GET|https://spatie.be/flare (Symfony\Component\HttpFoundation\Request)', originalType: Request::class),
        ],
    ],
    yield 'with sensitive parameter' => [
        TraceArguments::create()->withSensitiveParameter('secret'),
        [
            new ProvidedArgument(
                'sensitive',
                reducedValue: version_compare(PHP_VERSION, '8.2', '>=')
                    ? 'object (SensitiveParameterValue)'
                    : 'secret',
                originalType: version_compare(PHP_VERSION, '8.2', '>=')
                    ? SensitiveParameterValue::class
                    : 'string'
            ),
        ],
    ],
    yield 'with called closure (no reflection possible)' => [
        TraceArguments::create()->withCalledClosure(), [
            new ProvidedArgument('0', reducedValue: 'string', originalType: 'string'),
            new ProvidedArgument('1', reducedValue: 'Europe/Brussels (DateTimeZone)', originalType: DateTimeZone::class),
            new ProvidedArgument('2', reducedValue: 42, originalType: 'int'),
            new ProvidedArgument('3', reducedValue: 69, originalType: 'int'),
        ],
    ],
    yield 'with stdClass' => [
        TraceArguments::create()->withStdClass(
            (object) [
                'simple' => 'string',
                'complex' => new DateTimeZone('Europe/Brussels'),
            ]
        ), [
            new ProvidedArgument(
                'class',
                reducedValue: [
                    'simple' => 'string',
                    'complex' => 'object (DateTimeZone)',
                ],
                originalType: stdClass::class),
        ],
    ],
    yield 'with too many arguments provided' => [
        TraceArguments::create()->withArray(['a', 'b', 'c'], ['d', 'e', 'f'], ['x', 'y', 'z']),
        [
            new ProvidedArgument('array', reducedValue: ['a', 'b', 'c'], originalType: 'array'),
            new ProvidedArgument('1', reducedValue: ['d', 'e', 'f'], originalType: 'array'),
            new ProvidedArgument('2', reducedValue: ['x', 'y', 'z'], originalType: 'array'),
        ],
    ],
    yield 'with not enough arguments provided' => [
        TraceArguments::create()->withNotEnoughArgumentsProvided(),
        [
            new ProvidedArgument('simple', reducedValue: 'provided', originalType: 'string'),
            new ProvidedArgument('object', reducedValue: null, originalType: 'null'),
            new ProvidedArgument('variadic', isVariadic: true, reducedValue: [], originalType: 'array'),
        ]
    ]
]);

it('will reduce values with enums', function () {
    $frame = TraceArguments::create()->withEnums(
        unitEnum: FakeUnitEnum::A,
        stringBackedEnum: FakeStringBackedEnum::A,
        intBackedEnum: FakeIntBackedEnum::A,
    );

    $reduced = (new ReduceArgumentsAction(ArgumentReducers::default()))->execute($frame);

    expect($reduced)->toEqual([
        (new ProvidedArgument('unitEnum', reducedValue: FakeUnitEnum::class.'::A', originalType: FakeUnitEnum::class))->toArray(),
        (new ProvidedArgument('stringBackedEnum', reducedValue: FakeStringBackedEnum::class.'::A', originalType: FakeStringBackedEnum::class))->toArray(),
        (new ProvidedArgument('intBackedEnum', reducedValue: FakeIntBackedEnum::class.'::A', originalType: FakeIntBackedEnum::class))->toArray(),
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
        (new ProvidedArgument('simple', reducedValue: 'string', originalType: 'string'))->toArray(),
        (new ProvidedArgument('object', reducedValue: 'object (DateTimeZone)', originalType: DateTimeZone::class))->toArray(),
        (new ProvidedArgument('variadic', isVariadic: true, reducedValue: [42, 69], originalType: 'array'))->toArray(),
    ]);
});

it('will transform an ProvidedArgument to array', function (
    ProvidedArgument $argument,
    array $expected
) {
    expect($argument->toArray())->toEqual($expected);
})->with(fn () => [
    yield 'base' => [
        new ProvidedArgument('string', reducedValue: 'string', originalType: 'string'),
        [
            'name' => 'string',
            'value' => 'string',
            'passed_by_reference' => false,
            'is_variadic' => false,
            'truncated' => false,
            'original_type' => 'string',
        ],
    ],
    yield 'base passed by reference' => [
        new ProvidedArgument('string', passedByReference: true, reducedValue: 'string', originalType: 'string'),
        [
            'name' => 'string',
            'value' => 'string',
            'passed_by_reference' => true,
            'is_variadic' => false,
            'truncated' => false,
            'original_type' => 'string',
        ],
    ],
    yield 'base variadic' => [
        new ProvidedArgument('string', isVariadic: true, reducedValue: 'string', originalType: 'array'),
        [
            'name' => 'string',
            'value' => 'string',
            'passed_by_reference' => false,
            'is_variadic' => true,
            'truncated' => false,
            'original_type' => 'array',
        ],
    ],
    yield 'base truncated' => [
        new ProvidedArgument('string', truncated: true, reducedValue: 'string', originalType: 'string'),
        [
            'name' => 'string',
            'value' => 'string',
            'passed_by_reference' => false,
            'is_variadic' => false,
            'truncated' => true,
            'original_type' => 'string',
        ],
    ],
    yield 'default' => [
        new ProvidedArgument('string', hasDefaultValue: true, defaultValue: 'string', defaultValueUsed: true, originalType: 'string'),
        [
            'name' => 'string',
            'value' => 'string',
            'passed_by_reference' => false,
            'is_variadic' => false,
            'truncated' => false,
            'original_type' => 'string',
        ],
    ],
]);
