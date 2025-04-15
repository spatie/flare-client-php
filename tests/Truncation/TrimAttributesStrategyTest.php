<?php


use Pest\Support\Arr;
use Spatie\FlareClient\Truncation\ReportTrimmer;
use Spatie\FlareClient\Truncation\TrimAttributesStrategy;


it('can trim long attributes in payload', function () {
    ReportTrimmer::setMaxPayloadSize(52428);

    foreach (TrimAttributesStrategy::thresholds() as $threshold) {
        [$payload, $expected] = createLargePayloadWithAttributes($threshold);

        $strategy = new TrimAttributesStrategy(new ReportTrimmer());
        expect($strategy->execute($payload))->toBe($expected);
    }
});

it('does not trim short attribute payloads', function () {
    ReportTrimmer::setMaxPayloadSize(52428);

    $payload = [
        'context' => [
            'queries' => [
                1, 2, 3, 4,
            ],
        ],
    ];

    $strategy = new TrimAttributesStrategy(new ReportTrimmer());

    $trimmedPayload = $strategy->execute($payload);

    expect($trimmedPayload)->toBe($payload);
});

it('will keep certain keys in the payload', function (){
    ReportTrimmer::setMaxPayloadSize(10);

    $payload = [
        'attributes' => [
            'http.request.method' => array_fill(0, 100, 'RANDOM'),
            'trimmable' => array_fill(0, 100, 'RANDOM'),
        ],
    ];

    $strategy = new TrimAttributesStrategy(new ReportTrimmer());

    $trimmedPayload = $strategy->execute($payload);

    expect($trimmedPayload['attributes']['http.request.method'])->toHaveCount(100);
    expect($trimmedPayload['attributes']['trimmable'])->toHaveCount(10);
});

// Helpers
function createLargePayloadWithAttributes($threshold)
{
    $payload = $expected = [
        'attributes' => [
            'queries' => [],
        ],
    ];

    $contextKeys = [];

    while (strlen(json_encode($payload)) < ReportTrimmer::getMaxPayloadSize()) {
        $payloadItems = range(0, $threshold + 10);

        $contextKeys[] = $contextKey = uniqid();

        $payload['attributes'][$contextKey][] = $payloadItems;
        $expected['attributes'][$contextKey][] = array_slice($payloadItems, $threshold * -1, $threshold);
    }

    foreach ($contextKeys as $contextKey) {
        $expected['attributes'][$contextKey] = array_slice($expected['attributes'][$contextKey], $threshold * -1, $threshold);
    }

    return [$payload, $expected];
}
