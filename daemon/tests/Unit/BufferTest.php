<?php

use Spatie\FlareDaemon\Buffer;

it('tracks size and oldest age for buffered items', function () {
    $buffer = new Buffer('api-key', 'errors', 10);

    $buffer->add(['message' => 'first'], 10.0);
    $buffer->add(['message' => 'second'], 12.0);

    expect($buffer->count())->toBe(2)
        ->and($buffer->shouldFlushBySize())->toBeTrue()
        ->and($buffer->oldestAge(15.0))->toBe(5.0);
});

it('can drain buffered items', function () {
    $buffer = new Buffer('api-key', 'errors', 1024);

    $buffer->add(['message' => 'normal'], 10.0);
    $buffer->add(['message' => 'later'], 11.0);

    $drained = $buffer->drain();

    expect($drained)->toHaveCount(2)
        ->and($buffer->count())->toBe(0)
        ->and($buffer->hasItems())->toBeFalse();
});
