<?php

use Spatie\FlareClient\Senders\Support\JsonEncodableSanitizer;

describe('sanitize', function () {

    it('returns the payload unchanged when the json is encodable', function () {
        $sanitizer = new JsonEncodableSanitizer;
        $payload = [
            'string' => 'foo',
            'int' => 123,
            'bool' => true,
            'null' => null,
            'array' => ['bar' => 'baz'],
        ];
        expect($sanitizer->sanitize($payload))
            ->toBe($payload);
    });

    it('replaces non-UTF-8 strings', function () {
        $sanitizer = new JsonEncodableSanitizer;
        $payload = [
            'non_encodable' => "\xB1\x31",
        ];

        $result = $sanitizer->sanitize($payload);

        expect($result['non_encodable'])
            ->not()
            ->toBe($payload['non_encodable']);
    });

    it('replaces non-encodable resources', function () {
        $resource = fopen('php://memory', 'r');

        try {
            $payload = ['non_encodable' => $resource];
            $sanitizer = new JsonEncodableSanitizer;
            $result = $sanitizer->sanitize($payload);

            expect($result['non_encodable'])
                ->not()->toBe($resource);

        } finally {
            fclose($resource);
        }

    });

    it('indicates replaced entries with a recognizable message', function () {
        $resource = fopen('php://memory', 'r');

        try {
            $sanitizer = new JsonEncodableSanitizer;
            $payload = [$resource, "\xB1\x31"];

            $result = $sanitizer->sanitize($payload);

            foreach ($result as $key => $value) {
                expect($value)
                    ->toContain(JsonEncodableSanitizer::SANITIZED_PAYLOAD_ENTRY_REPLACEMENT_PREFIX);
            }

        } finally {
            fclose($resource);
        }
    });

    it('adds the JsonException message to the replacing strings', function () {
        $sanitizer = new JsonEncodableSanitizer;

        $payload = ["\xB1\x31"];
        $result = $sanitizer->sanitize($payload);

        try {
            json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            expect($result[0])->toContain($e->getMessage());
        }

    });

    it('replaces non-encodable values recursively', function () {
        $payload = [
            'level_one' => "\xB1\x31",
            'level_two' => ["\xB1\x31"],
            'level_three' => [
                ["\xB1\x31"],
            ],
        ];

        $sanitizer = new JsonEncodableSanitizer;
        $result = $sanitizer->sanitize($payload);

        expect($result['level_one'])->toContain(JsonEncodableSanitizer::SANITIZED_PAYLOAD_ENTRY_REPLACEMENT_PREFIX);
        expect($result['level_two'][0])->toContain(JsonEncodableSanitizer::SANITIZED_PAYLOAD_ENTRY_REPLACEMENT_PREFIX);
        expect($result['level_three'][0][0])->toContain(JsonEncodableSanitizer::SANITIZED_PAYLOAD_ENTRY_REPLACEMENT_PREFIX);

    });

    it('preserves valid nested data alongside replaced non-encodable items', function () {
        $sanitizer = new JsonEncodableSanitizer;
        $payload = [
            'valid' => 'data',
            'nested' => [
                'valid_inner' => 123,
                'invalid_inner' => "\xB1\x31",
            ],
        ];
        $result = $sanitizer->sanitize($payload);

        expect($result['valid'])->toBe('data');

        expect($result['nested']['valid_inner'])->toBe(123);

        expect($result['nested']['invalid_inner'])
            ->toContain(JsonEncodableSanitizer::SANITIZED_PAYLOAD_ENTRY_REPLACEMENT_PREFIX);
    });

    it('sanitizes deeply nested arrays with mixed valid and invalid data', function () {
        $payload = [
            'level_one_valid' => 'ok',
            'level_one_invalid' => "\xB1\x31",
            'level_one_array' => [
                'level_two_valid' => true,
                'level_two_invalid' => "\xB1\x31",
                'level_two_array' => [
                    'level_three_valid' => 42,
                    'level_three_invalid' => "\xB1\x31",
                ],
            ],
        ];
        $sanitizer = new JsonEncodableSanitizer;
        $result = $sanitizer->sanitize($payload);

        expect($result['level_one_valid'])->toBe('ok');
        expect($result['level_one_invalid'])
            ->toContain(JsonEncodableSanitizer::SANITIZED_PAYLOAD_ENTRY_REPLACEMENT_PREFIX);

        expect($result['level_one_array']['level_two_valid'])->toBe(true);
        expect($result['level_one_array']['level_two_invalid'])
            ->toContain(JsonEncodableSanitizer::SANITIZED_PAYLOAD_ENTRY_REPLACEMENT_PREFIX);

        expect($result['level_one_array']['level_two_array']['level_three_valid'])->toBe(42);
        expect($result['level_one_array']['level_two_array']['level_three_invalid'])
            ->toContain(JsonEncodableSanitizer::SANITIZED_PAYLOAD_ENTRY_REPLACEMENT_PREFIX);
    });

});
