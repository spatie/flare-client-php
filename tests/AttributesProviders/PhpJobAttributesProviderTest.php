<?php

use Spatie\FlareClient\AttributesProviders\PhpJobAttributesProvider;

it('returns an empty attributes array', function () {
    $provider = new PhpJobAttributesProvider('process-podcast');

    expect($provider->toArray())->toBe([]);
});

it('exposes the job name and class through accessors', function () {
    $provider = new PhpJobAttributesProvider('process-podcast', 'App\\Jobs\\ProcessPodcast');

    expect($provider->jobName())->toBe('process-podcast');
    expect($provider->jobClass())->toBe('App\\Jobs\\ProcessPodcast');
});
