<?php

use Spatie\FlareDaemon\Support\Output;

it('writes debug messages only when verbose is enabled', function () {
    $stdout = fopen('php://temp', 'w+');
    assert(is_resource($stdout));

    $quiet = new Output($stdout, null, verbose: false);
    $quiet->debug('should not appear');

    rewind($stdout);
    expect(stream_get_contents($stdout))->toBe('');

    $verbose = new Output($stdout, null, verbose: true);
    $verbose->debug('should appear');

    rewind($stdout);
    expect(stream_get_contents($stdout))->toContain('DEBUG')
        ->toContain('should appear');
});

it('always writes info messages regardless of verbose setting', function () {
    $stdout = fopen('php://temp', 'w+');
    assert(is_resource($stdout));

    $output = new Output($stdout, null, verbose: false);
    $output->info('always visible');

    rewind($stdout);
    expect(stream_get_contents($stdout))->toContain('INFO')
        ->toContain('always visible');
});
