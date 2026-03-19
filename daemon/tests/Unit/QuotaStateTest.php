<?php

use Spatie\FlareDaemon\QuotaState;

it('pauses and resumes a single key and type', function () {
    $quotaState = new QuotaState();

    $quotaState->pause('api-key', 'traces', 10.0, 'Trace quota exceeded');

    expect($quotaState->isPaused('api-key', 'traces', 9.0))->toBeTrue()
        ->and($quotaState->reason('api-key', 'traces'))->toBe('Trace quota exceeded');

    expect($quotaState->resumeExpired(10.0))->toBe([
        ['api_key' => 'api-key', 'type' => 'traces'],
    ]);

    expect($quotaState->isPaused('api-key', 'traces', 11.0))->toBeFalse();
});

it('can permanently pause all types for an invalid api key', function () {
    $quotaState = new QuotaState();

    $quotaState->pauseAll('api-key', 'Invalid API key');

    expect($quotaState->isPermanent('api-key', 'errors'))->toBeTrue()
        ->and($quotaState->isPermanent('api-key', 'traces'))->toBeTrue()
        ->and($quotaState->isPermanent('api-key', 'logs'))->toBeTrue();
});
