<?php


use Spatie\FlareClient\Truncation\ReportTrimmer;
use Spatie\FlareClient\Truncation\TrimStringsStrategy;

it('can trim long strings in payload', function () {
    foreach (TrimStringsStrategy::thresholds() as $threshold) {
        [$payload, $expected] = createLargePayload($threshold);

        $strategy = new TrimStringsStrategy(new ReportTrimmer());
        $this->assertSame($expected, $strategy->execute($payload));
    }
});

it('does not trim short payloads', function () {
    $payload = [
        'data' => [
            'body' => 'short',
            'nested' => [
                'message' => 'short',
            ],
        ],
    ];

    $strategy = new TrimStringsStrategy(new ReportTrimmer());

    $trimmedPayload = $strategy->execute($payload);

    $this->assertSame($payload, $trimmedPayload);
});

// Helpers
function createLargePayload($threshold)
{
    $payload = $expected = [
        'data' => [
            'messages' => [],
        ],
    ];

    while (strlen(json_encode($payload)) < ReportTrimmer::getMaxPayloadSize()) {
        $payload['data']['messages'][] = str_repeat('A', $threshold + 10);
        $expected['data']['messages'][] = str_repeat('A', $threshold);
    }

    return [$payload, $expected];
}
