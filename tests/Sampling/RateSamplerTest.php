<?php

use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\Sampling\RateSampler;

it('never samples when rate is zero', function () {
    $sampler = new RateSampler(['rate' => 0]);
    $entryPoint = new EntryPoint(EntryPointType::Web, 'http://localhost');

    for ($i = 0; $i < 10000; $i++) {
        expect($sampler->shouldSample($entryPoint))->toBeFalse();
    }
});

it('always samples when rate is one', function () {
    $sampler = new RateSampler(['rate' => 1]);
    $entryPoint = new EntryPoint(EntryPointType::Web, 'http://localhost');

    for ($i = 0; $i < 10000; $i++) {
        expect($sampler->shouldSample($entryPoint))->toBeTrue();
    }
});

it('uses default sample rate of 10 percent', function () {
    $sampler = new RateSampler([]);
    $entryPoint = new EntryPoint(EntryPointType::Web, 'http://localhost');

    $tries = 10000;
    $sampledCount = 0;

    for ($i = 0; $i < $tries; $i++) {
        if ($sampler->shouldSample($entryPoint)) {
            $sampledCount++;
        }
    }

    $percentage = ($sampledCount / $tries) * 100;

    expect($percentage)->toBeGreaterThanOrEqual(8)
        ->and($percentage)->toBeLessThanOrEqual(12);
});

it('can manually define a sample rate', function () {
    $sampler = new RateSampler(['rate' => 0.3]);
    $entryPoint = new EntryPoint(EntryPointType::Web, 'http://localhost');

    $tries = 10000;
    $sampledCount = 0;

    for ($i = 0; $i < $tries; $i++) {
        if ($sampler->shouldSample($entryPoint)) {
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

it('honors parentSampled true regardless of rate', function () {
    $sampler = new RateSampler(['rate' => 0]);
    $entryPoint = new EntryPoint(EntryPointType::Web, 'http://localhost');

    for ($i = 0; $i < 100; $i++) {
        expect($sampler->shouldSample($entryPoint, true))->toBeTrue();
    }
});

it('honors parentSampled false regardless of rate', function () {
    $sampler = new RateSampler(['rate' => 1]);
    $entryPoint = new EntryPoint(EntryPointType::Web, 'http://localhost');

    for ($i = 0; $i < 100; $i++) {
        expect($sampler->shouldSample($entryPoint, false))->toBeFalse();
    }
});

it('rolls the rate when parentSampled is null', function () {
    $sampler = new RateSampler(['rate' => 0]);
    $entryPoint = new EntryPoint(EntryPointType::Web, 'http://localhost');

    expect($sampler->shouldSample($entryPoint, null))->toBeFalse();
});
