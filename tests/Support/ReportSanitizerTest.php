<?php

use Spatie\FlareClient\Support\ReportSanitizer;

it('returns payload unchanged when json is encodable', function () {
    $payload = [
        'string' => 'foo',
        'int' => 123,
        'bool' => true,
        'null' => null,
        'array' => ['bar' => 'baz'],
    ];

    expect(ReportSanitizer::sanitizePayload($payload))->toBe($payload);
});

it('replaces non-encodable strings', function () {
    $payload = [
        'string' => 'foo',
        'int' => 123,
        'bool' => true,
        'null' => null,
        'array' => ['bar' => 'baz'],
        'non-encodable' => "\xB1\x31",
    ];
    $result = ReportSanitizer::sanitizePayload($payload);

    expect($result)->not()->toBe($payload);

    foreach ($result as $key => $value) {
        if ($key === 'non-encodable') {
            expect($value)->not()->toBe($payload[$key]);
        } else {
            expect($value)->toBe($payload[$key]);
        }

    }
});

it('indicates replaced entries with a recognizable message', function () {
    $payload = [
        'non-encodable' => "\xA8\x32",
    ];
    $result = ReportSanitizer::sanitizePayload($payload);

    expect($result['non-encodable'])->toContain(ReportSanitizer::REPLACED_ENTRY_PREFIX);
});

it('recursively replaces non-encodable values in nested arrays', function () {
    $invalidString = "\xB1\x31";

    $payload = [
        'nested' => [
            'broken' => $invalidString,
        ],
    ];

    $result = ReportSanitizer::sanitizePayload($payload);

    expect($result['nested']['broken'])->not()->toBe($payload['nested']['broken']);
    expect($result['nested']['broken'])->toContain(ReportSanitizer::REPLACED_ENTRY_PREFIX);

});

it('replaces non-string non-encodable values', function () {
    $resource = fopen('php://memory', 'r');
    try {

        $payload = ['res' => $resource];
        $result = ReportSanitizer::sanitizePayload($payload);
        expect($result['res'])->toContain(ReportSanitizer::REPLACED_ENTRY_PREFIX);

    } finally {
        fclose($resource);
    }
});

it('enables corrupt report data arrays to be json_encodable', function () {
    $resource = fopen('php://memory', 'r');
    $invalidString = "\xB1\x31";

    try {
        $data = [
            'stacktrace' => [
                [
                    'file' => 'test.php',
                    'lineNumber' => 123,
                    'method' => 'testMethod',
                    'class' => 'TestClass',
                    'codeSnippet' => ['line1' => '<?php echo "Hello";'],
                    'arguments' => null,
                    'isApplicationFrame' => true,
                ],
            ],
            'exceptionClass' => 'RuntimeException',
            'message' => $invalidString,
            'isLog' => false,
            'timeUs' => 123456789,
            'attributes' => [
                'valid' => 'ok',
                'invalid' => $resource,
            ],
        ];

        expect(fn () => json_encode($data, JSON_THROW_ON_ERROR))->toThrow(JsonException::class);

        $sanitized = ReportSanitizer::sanitizePayload($data);

        expect(fn () => json_encode($sanitized, JSON_THROW_ON_ERROR))->not()->toThrow(JsonException::class);

    } finally {
        fclose($resource);
    }
});
