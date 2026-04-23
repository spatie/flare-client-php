<?php

use Spatie\FlareClient\Support\Ids;

it('generates a valid trace id', function () {
    $ids = new Ids();
    $traceId = $ids->trace();

    expect($traceId)->toBeString();
    expect(strlen($traceId))->toBe(32);
    expect(ctype_xdigit($traceId))->toBeTrue();
});

it('generates unique trace ids', function () {
    $ids = new Ids();
    $traceId1 = $ids->trace();
    $traceId2 = $ids->trace();

    expect($traceId1)->not()->toBe($traceId2);
});

it('generates a valid span id', function () {
    $ids = new Ids();
    $spanId = $ids->span();

    expect($spanId)->toBeString();
    expect(strlen($spanId))->toBe(16);
    expect(ctype_xdigit($spanId))->toBeTrue();
});

it('generates unique span ids', function () {
    $ids = new Ids();
    $spanId1 = $ids->span();
    $spanId2 = $ids->span();

    expect($spanId1)->not()->toBe($spanId2);
});

it('generates a valid uuid v4', function () {
    $ids = new Ids();
    $uuid = $ids->uuid();

    expect($uuid)->toBeString();
    expect(strlen($uuid))->toBe(36);
    expect($uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
});

it('generates unique uuids', function () {
    $ids = new Ids();
    $uuid1 = $ids->uuid();
    $uuid2 = $ids->uuid();

    expect($uuid1)->not()->toBe($uuid2);
});

it('generates a traceparent with sampling enabled', function () {
    $ids = new Ids();
    $traceId = 'abcd1234567890abcdef1234567890ab';
    $spanId = '1234567890abcdef';

    $traceParent = $ids->traceParent($traceId, $spanId, true);

    expect($traceParent)->toBe('00-abcd1234567890abcdef1234567890ab-1234567890abcdef-01');
});

it('generates a traceparent with sampling disabled', function () {
    $ids = new Ids();
    $traceId = 'abcd1234567890abcdef1234567890ab';
    $spanId = '1234567890abcdef';

    $traceParent = $ids->traceParent($traceId, $spanId, false);

    expect($traceParent)->toBe('00-abcd1234567890abcdef1234567890ab-1234567890abcdef-00');
});

it('parses a valid traceparent', function () {
    $ids = new Ids();
    $traceParent = '00-abcd1234567890abcdef1234567890ab-1234567890abcdef-01';

    $parsed = $ids->parseTraceparent($traceParent);

    expect($parsed)->toBe([
        'traceId' => 'abcd1234567890abcdef1234567890ab',
        'parentSpanId' => '1234567890abcdef',
        'sampling' => true,
    ]);
});

it('parses a traceparent with sampling disabled', function () {
    $ids = new Ids();
    $traceParent = '00-abcd1234567890abcdef1234567890ab-1234567890abcdef-00';

    $parsed = $ids->parseTraceparent($traceParent);

    expect($parsed)->toBe([
        'traceId' => 'abcd1234567890abcdef1234567890ab',
        'parentSpanId' => '1234567890abcdef',
        'sampling' => false,
    ]);
});

it('returns null when parsing invalid traceparent with wrong number of parts', function () {
    $ids = new Ids();
    $traceParent = '00-abcd1234567890abcdef1234567890ab-1234567890abcdef';

    $parsed = $ids->parseTraceparent($traceParent);

    expect($parsed)->toBeNull();
});

it('returns null when parsing invalid traceparent with wrong version', function () {
    $ids = new Ids();
    $traceParent = '01-abcd1234567890abcdef1234567890ab-1234567890abcdef-01';

    $parsed = $ids->parseTraceparent($traceParent);

    expect($parsed)->toBeNull();
});

it('sets traceparent sampling to true', function () {
    $ids = new Ids();
    $traceParent = '00-abcd1234567890abcdef1234567890ab-1234567890abcdef-00';

    $updated = $ids->setTraceparentSampling($traceParent, true);

    expect($updated)->toBe('00-abcd1234567890abcdef1234567890ab-1234567890abcdef-01');
});

it('sets traceparent sampling to false', function () {
    $ids = new Ids();
    $traceParent = '00-abcd1234567890abcdef1234567890ab-1234567890abcdef-01';

    $updated = $ids->setTraceparentSampling($traceParent, false);

    expect($updated)->toBe('00-abcd1234567890abcdef1234567890ab-1234567890abcdef-00');
});

it('returns null when setting sampling on invalid traceparent', function () {
    $ids = new Ids();
    $traceParent = 'invalid-traceparent';

    $updated = $ids->setTraceparentSampling($traceParent, true);

    expect($updated)->toBeNull();
});
