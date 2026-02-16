<?php

use Spatie\FlareClient\Truncation\ReportTrimmer;
use Spatie\FlareClient\Truncation\TrimPreviousStrategy;

it('can trim previous throwables until the data fits', function () {
    ReportTrimmer::setMaxPayloadSize(52_428);

    $payload = [
        'previous' => [
            'a' => createLargePreviousPayload(50_000),
            'b' => createLargePreviousPayload(50_000),
            'c' => createLargePreviousPayload(50_000),
        ],
    ];

    $strategy = new TrimPreviousStrategy(new ReportTrimmer());

    expect($strategy->execute($payload)['previous'])
        ->toHaveKey('a')
        ->not->toHaveKey('b')
        ->not->toHaveKey('c');
});


it('can trim previous throwables until the data fits and tries to keep the latest throwables', function () {
    ReportTrimmer::setMaxPayloadSize(52_428);

    $payload = [
        'previous' => [
            'a' => createLargePreviousPayload(25_000),
            'b' => createLargePreviousPayload(25_000),
            'c' => createLargePreviousPayload(25_000),
        ],
    ];

    $strategy = new TrimPreviousStrategy(new ReportTrimmer());

    expect($strategy->execute($payload)['previous'])
        ->toHaveKey('a')
        ->toHaveKey('b')
        ->not->toHaveKey('c');
});

function createLargePreviousPayload(int $size): array
{
    $payload = [];

    while (strlen(json_encode($payload)) < $size) {
        $payloadItems = range(0, 10);

        $contextKey = uniqid();

        $payload[$contextKey][] = $payloadItems;
    }

    return $payload;
}
