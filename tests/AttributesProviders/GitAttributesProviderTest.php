<?php

use Spatie\FlareClient\AttributesProviders\GitAttributesProvider;

it('can collect git info from files', function () {
    $provider = new GitAttributesProvider();
    $result = $provider->toArray(useProcess: false);

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('git.hash')
        ->and($result['git.hash'])->toBeString()
        ->and($result['git.hash'])->toHaveLength(40)
        ->and($result)->toHaveKey('git.remote')
        ->and($result['git.remote'])->toBeString()
        ->and($result['git.remote'])->toContain('flare-client-php');

    if (array_key_exists('git.branch', $result)) {
        expect($result['git.branch'])->toBeString();
    }

    if (array_key_exists('git.message', $result)) {
        // Packed git objects on ci are not supported
        expect($result['git.message'])->toBeString()->and($result['git.message'])->not->toBeEmpty();
    }
});


it('can collect git info using process', function () {
    $provider = new GitAttributesProvider();
    $result = $provider->toArray(useProcess: true);

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('git.hash')
        ->and($result['git.hash'])->toBeString()
        ->and($result['git.hash'])->toHaveLength(40)
        ->and($result)->toHaveKey('git.message')
        ->and($result['git.message'])->toBeString()
        ->and($result)->toHaveKey('git.is_dirty')
        ->and($result['git.is_dirty'])->toBeBool();

    if (array_key_exists('git.branch', $result)) {
        expect($result['git.branch'])->toBeString();
    }
});

it('file-based and process-based modes return same hash and branch', function () {
    $provider = new GitAttributesProvider();
    $fileResult = $provider->toArray(useProcess: false);

    $provider2 = new GitAttributesProvider(); // Create new instance to avoid cache
    $processResult = $provider2->toArray(useProcess: true);

    expect($fileResult['git.hash'])->toBe($processResult['git.hash']);

    if (array_key_exists('git.branch', $fileResult) && array_key_exists('git.branch', $processResult)) {
        expect($fileResult['git.branch'])->toBe($processResult['git.branch']);
    }
});

it('file-based and process-based modes return same commit message when available', function () {
    $provider = new GitAttributesProvider();
    $fileResult = $provider->toArray(useProcess: false);

    $provider2 = new GitAttributesProvider(); // Create new instance to avoid cache
    $processResult = $provider2->toArray(useProcess: true);

    expect($processResult)->toHaveKey('git.message');

    if (array_key_exists('git.message', $fileResult)) {
        // Packed git objects on ci are not supported
        expect($fileResult['git.message'])->toBe($processResult['git.message']);
    }
});

it('returns empty array when path does not have git directory', function () {
    $provider = new GitAttributesProvider(applicationPath: sys_get_temp_dir());
    $result = $provider->toArray(useProcess: false);

    expect($result)->toBeEmpty();
});
