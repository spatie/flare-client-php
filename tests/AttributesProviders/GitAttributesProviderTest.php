<?php

use Spatie\FlareClient\AttributesProviders\GitAttributesProvider;

it('can collect git info from files', function () {
    $provider = new GitAttributesProvider();
    $result = $provider->toArray(useProcess: false);

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('git.hash')
        ->and($result['git.hash'])->toBeString()
        ->and($result['git.hash'])->toHaveLength(40)
        ->and($result)->toHaveKey('git.branch')
        ->and($result['git.branch'])->toBeString()
        ->and($result)->toHaveKey('git.message')
        ->and($result['git.message'])->toBeString()
        ->and($result['git.message'])->not->toBeEmpty()
        ->and($result)->toHaveKey('git.remote')
        ->and($result['git.remote'])->toBeString()
        ->and($result['git.remote'])->toContain('flare-client-php');
});


it('can collect git info using process', function () {
    $provider = new GitAttributesProvider();
    $result = $provider->toArray(useProcess: true);

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('git.hash')
        ->and($result['git.hash'])->toBeString()
        ->and($result['git.hash'])->toHaveLength(40)
        ->and($result)->toHaveKey('git.branch')
        ->and($result['git.branch'])->toBeString()
        ->and($result)->toHaveKey('git.message')
        ->and($result['git.message'])->toBeString()
        ->and($result)->toHaveKey('git.is_dirty')
        ->and($result['git.is_dirty'])->toBeBool();
});

it('file-based and process-based modes return same hash and branch', function () {
    $provider = new GitAttributesProvider();

    $fileResult = $provider->toArray(useProcess: false);

    // Create new instance to avoid cache
    $provider2 = new GitAttributesProvider();
    $processResult = $provider2->toArray(useProcess: true);

    expect($fileResult['git.hash'])->toBe($processResult['git.hash'])
        ->and($fileResult['git.branch'])->toBe($processResult['git.branch']);
});

it('file-based and process-based modes return same commit message', function () {
    $provider = new GitAttributesProvider();

    $fileResult = $provider->toArray(useProcess: false);

    // Create new instance to avoid cache
    $provider2 = new GitAttributesProvider();
    $processResult = $provider2->toArray(useProcess: true);

    expect($fileResult['git.message'])->toBe($processResult['git.message']);
});

it('returns empty array when path does not have git directory', function () {
    $provider = new GitAttributesProvider(applicationPath: sys_get_temp_dir());
    $result = $provider->toArray(useProcess: false);

    expect($result)->toBeEmpty();
});
