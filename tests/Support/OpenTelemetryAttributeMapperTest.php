<?php

use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Support\OpenTelemetryAttributeMapper;

it('can map attributes', function () {
    $mapper = new OpenTelemetryAttributeMapper();

    $attributes = $mapper->attributesToOpenTelemetry([
        'string' => 'string',
        'bool' => true,
        'int' => 1,
        'float' => 1.1,
        'array' => ['a', 'b'],
        'assoc' => ['a' => 'b'],
        'null' => null,
        'object' => (object) ['value' => 'object'],
        'enum' => SpanType::Command,
    ]);

    expect($attributes[0])->toBe(['key' => 'string', 'value' => ['stringValue' => 'string']]);
    expect($attributes[1])->toBe(['key' => 'bool', 'value' => ['boolValue' => true]]);
    expect($attributes[2])->toBe(['key' => 'int', 'value' => ['intValue' => 1]]);
    expect($attributes[3])->toBe(['key' => 'float', 'value' => ['doubleValue' => 1.1]]);
    expect($attributes[4])->toBe(['key' => 'array', 'value' => ['arrayValue' => [
        'values' => [
            ['stringValue' => 'a'],
            ['stringValue' => 'b'],
        ],
    ]]]);
    expect($attributes[5])->toBe(['key' => 'assoc', 'value' => ['kvlistValue' => [
        'values' => [['key' => 'a', 'value' => ['stringValue' => 'b']]],
    ]]]);
    expect($attributes[6])->toBe(['key' => 'object', 'value' => ['stringValue' => '{"value":"object"}']]);
    expect($attributes[7])->toBe(['key' => 'enum', 'value' => ['stringValue' => SpanType::Command->value]]);
});

it('can map attributes back to PHP', function () {
    $mapper = new OpenTelemetryAttributeMapper();

    $attributes = [
        ['key' => 'string', 'value' => ['stringValue' => 'string']],
        ['key' => 'bool', 'value' => ['boolValue' => true]],
        ['key' => 'int', 'value' => ['intValue' => 1]],
        ['key' => 'float', 'value' => ['doubleValue' => 1.1]],
        ['key' => 'array', 'value' => ['arrayValue' => [
            'values' => [
                ['stringValue' => 'a'],
                ['stringValue' => 'b'],
            ],
        ]]],
        ['key' => 'assoc', 'value' => ['kvlistValue' => [
            'values' => [['key' => 'a', 'value' => ['stringValue' => 'b']]],
        ]]],
        ['key' => 'object', 'value' => ['stringValue' => '{"value":"object"}']],
        ['key' => 'enum', 'value' => ['stringValue' => SpanType::Command->value]],
    ];

    $phpAttributes = $mapper->attributesToPHP($attributes);

    expect($phpAttributes['string'])->toBe('string');
    expect($phpAttributes['bool'])->toBe(true);
    expect($phpAttributes['int'])->toBe(1);
    expect($phpAttributes['float'])->toBe(1.1);
    expect($phpAttributes['array'])->toBe(['a', 'b']);
    expect($phpAttributes['assoc'])->toBe(['a' => 'b']);
    expect($phpAttributes['object'])->toBe(['value' => 'object']);
    expect($phpAttributes['enum'])->toBe(SpanType::Command->value);
});
