<?php

use Spatie\FlareClient\Recorders\ContextRecorder\ContextRecorder;

it('groups context entries under their group name', function () {
    $recorder = new ContextRecorder();

    $recorder->record('user', 'id', 42);
    $recorder->record('user', 'name', 'Ruben');
    $recorder->record('request', 'id', 'abc');

    expect($recorder->toArray())->toBe([
        'user' => ['id' => 42, 'name' => 'Ruben'],
        'request' => ['id' => 'abc'],
    ]);
});

it('accepts an array of key/value pairs in a single call', function () {
    $recorder = new ContextRecorder();

    $recorder->record('user', ['id' => 42, 'name' => 'Ruben']);

    expect($recorder->toArray())->toBe([
        'user' => ['id' => 42, 'name' => 'Ruben'],
    ]);
});

it('overwrites existing keys on subsequent record calls', function () {
    $recorder = new ContextRecorder();

    $recorder->record('user', 'id', 1);
    $recorder->record('user', 'id', 2);

    expect($recorder->toArray())->toBe(['user' => ['id' => 2]]);
});

it('recursively merges nested array values', function () {
    $recorder = new ContextRecorder();

    $recorder->record('user', ['profile' => ['name' => 'Ruben', 'tags' => ['a']]]);
    $recorder->record('user', ['profile' => ['email' => 'ruben@spatie.be']]);

    expect($recorder->toArray())->toBe([
        'user' => [
            'profile' => [
                'name' => 'Ruben',
                'tags' => ['a'],
                'email' => 'ruben@spatie.be',
            ],
        ],
    ]);
});


it('clears all stored context on reset', function () {
    $recorder = new ContextRecorder();

    $recorder->record('user', 'id', 42);
    $recorder->reset();

    expect($recorder->toArray())->toBe([]);
});
