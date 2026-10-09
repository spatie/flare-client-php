<?php

use Spatie\FlareClient\Truncation\ReportTrimmer;

it('trims a payload with a very large string without encoding it in full', function () {
    ReportTrimmer::setMaxPayloadSize(524_288);

    $payload = [
        'previous' => [],
        'stacktrace' => [],
        'attributes' => [
            'http.request.body.contents' => [
                'upload' => str_repeat('A', 50 * 1024 * 1024),
            ],
        ],
    ];

    memory_reset_peak_usage();
    $memoryBeforeTrimming = memory_get_usage();

    $trimmedPayload = (new ReportTrimmer())->trim($payload);

    $memoryUsedWhileTrimming = memory_get_peak_usage() - $memoryBeforeTrimming;

    expect($memoryUsedWhileTrimming)->toBeLessThan(ReportTrimmer::getMaxPayloadSize() * 4);
    expect(strlen(json_encode($trimmedPayload)))->toBeLessThanOrEqual(ReportTrimmer::getMaxPayloadSize());
});
