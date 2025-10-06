<?php

use Spatie\FlareClient\Sampling\RateSampler;

it('never samples when rate is zero', function () {
    $sampler = new RateSampler(['rate' => 0]);

    for ($i = 0; $i < 10000; $i++) {
        expect($sampler->shouldSample([]))->toBeFalse();
    }
});

it('always samples when rate is one', function () {
    $sampler = new RateSampler(['rate' => 1]);

    for ($i = 0; $i < 10000; $i++) {
        expect($sampler->shouldSample([]))->toBeTrue();
    }
});

it('uses default sample rate of 10 percent', function () {
    $sampler = new RateSampler([]);

    $tries = 10000;
    $sampledCount = 0;

    for ($i = 0; $i < $tries; $i++) {
        if ($sampler->shouldSample([])) {
            $sampledCount++;
        }
    }

    $percentage = ($sampledCount / $tries) * 100;

    expect($percentage)->toBeGreaterThanOrEqual(8)
        ->and($percentage)->toBeLessThanOrEqual(12);
});

it('can manually define a sample rate', function () {
    $sampler = new RateSampler(['rate' => 0.3]);

    $tries = 10000;
    $sampledCount = 0;

    for ($i = 0; $i < $tries; $i++) {
        if ($sampler->shouldSample([])) {
            $sampledCount++;
        }
    }

    $percentage = ($sampledCount / $tries) * 100;

    expect($percentage)->toBeGreaterThanOrEqual(28)
        ->and($percentage)->toBeLessThanOrEqual(32);
});

it('cannot define a negative sample rate', function () {
    new RateSampler(['rate' => -0.5]);
})->throws(InvalidArgumentException::class, 'Rate must be between 0 and 1');

it('cannot define a sample rate above 1', function () {
    new RateSampler(['rate' => 1.5]);
})->throws(InvalidArgumentException::class, 'Rate must be between 0 and 1');
