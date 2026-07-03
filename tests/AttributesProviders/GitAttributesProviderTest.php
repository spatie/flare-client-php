<?php

use Spatie\FlareClient\AttributesProviders\GitAttributesProvider;

beforeEach(fn () => GitAttributesProvider::clearCache());

it('can collect git info from files', function () {
    $provider = new GitAttributesProvider(useProcess: false);
    $result = $provider->toArray();

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
    $provider = new GitAttributesProvider(useProcess: true);
    $result = $provider->toArray();

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
    $fileResult = (new GitAttributesProvider(useProcess: false))->toArray();

    GitAttributesProvider::clearCache();

    $processResult = (new GitAttributesProvider(useProcess: true))->toArray();

    expect($fileResult['git.hash'])->toBe($processResult['git.hash']);

    if (array_key_exists('git.branch', $fileResult) && array_key_exists('git.branch', $processResult)) {
        expect($fileResult['git.branch'])->toBe($processResult['git.branch']);
    }
});

it('file-based and process-based modes return same commit message when available', function () {
    $fileResult = (new GitAttributesProvider(useProcess: false))->toArray();

    GitAttributesProvider::clearCache();

    $processResult = (new GitAttributesProvider(useProcess: true))->toArray();

    expect($processResult)->toHaveKey('git.message');

    if (array_key_exists('git.message', $fileResult)) {
        // Packed git objects on ci are not supported
        expect($fileResult['git.message'])->toBe($processResult['git.message']);
    }
});

it('strips embedded credentials from the git remote url', function () {
    $baseDirectory = sys_get_temp_dir().'/flare-git-'.uniqid();
    mkdir($baseDirectory.'/.git', 0777, true);
    file_put_contents($baseDirectory.'/.git/HEAD', str_repeat('a', 40));
    file_put_contents($baseDirectory.'/.git/config', <<<'CONFIG'
        [remote "origin"]
            url = https://user:password@development.intern.securepoint.de/vtigercrm/vtigercrm.git
        CONFIG);

    $result = (new GitAttributesProvider(applicationPath: $baseDirectory, useProcess: false))->toArray();

    expect($result['git.remote'])->toBe('https://development.intern.securepoint.de/vtigercrm/vtigercrm.git');

    unlink($baseDirectory.'/.git/HEAD');
    unlink($baseDirectory.'/.git/config');
    rmdir($baseDirectory.'/.git');
    rmdir($baseDirectory);
});

it('returns empty array when path does not have git directory', function () {
    $provider = new GitAttributesProvider(applicationPath: sys_get_temp_dir(), useProcess: false);
    $result = $provider->toArray();

    expect($result)->toBeEmpty();
});
