<?php

use React\EventLoop\Loop;
use Spatie\FlareDaemon\CheckForUpdates;

it('invokes the callback when composer lock changes', function () {
    $path = tempnam(sys_get_temp_dir(), 'flare-lock-');
    file_put_contents($path, '{"packages":[]}');

    $changed = false;

    $checker = new CheckForUpdates(
        loop: Loop::get(),
        composerLockPath: $path,
        output: makeOutput(),
        onChange: function () use (&$changed) {
            $changed = true;
        },
        intervalSeconds: 0.01,
    );

    file_put_contents($path, '{"packages":[{"name":"spatie/flare-client-php"}]}');
    $checker->check();

    expect($changed)->toBeTrue();

    unlink($path);
});

it('ignores unreadable or missing files', function () {
    $changed = false;

    $checker = new CheckForUpdates(
        loop: Loop::get(),
        composerLockPath: sys_get_temp_dir().'/missing-flare-lock.json',
        output: makeOutput(),
        onChange: function () use (&$changed) {
            $changed = true;
        },
    );

    $checker->check();

    expect($changed)->toBeFalse();
});
