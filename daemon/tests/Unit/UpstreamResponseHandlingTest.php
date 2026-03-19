<?php

use Spatie\FlareDaemon\Upstream;

it('extracts the best available reason from an upstream response body', function () {
    expect(Upstream::reasonFromResponseBody(['message' => 'Trace quota exceeded'], 429))->toBe('Trace quota exceeded')
        ->and(Upstream::reasonFromResponseBody('Rate limit exceeded', 429))->toBe('Rate limit exceeded')
        ->and(Upstream::reasonFromResponseBody(null, 429))->toBe('HTTP 429');
});

it('truncates long upstream bodies in logs', function () {
    expect(Upstream::summarizeBody(str_repeat('a', 250)))->toHaveLength(203);
});
