<?php

use Spatie\FlareClient\Glows\Glow;
use Spatie\FlareClient\Glows\GlowRecorder;

it('is initially empty', function () {
    $recorder = new GlowRecorder();

    expect($recorder->glows())->toHaveCount(0);
});

it('stores glows', function () {
    $recorder = new GlowRecorder();

    $glow = new Glow('Some name', 'info', [
        'some' => 'metadata',
    ]);

    $recorder->record($glow);

    expect($recorder->glows())->toHaveCount(1);

    expect($recorder->glows()[0])->toBe($glow);
});

it('does not store more than the max defined number of glows', function () {
    $recorder = new GlowRecorder();

    $crumb1 = new Glow('One');
    $crumb2 = new Glow('Two');

    foreach (range(1, 40) as $i) {
        $recorder->record($crumb1);
    }

    $recorder->record($crumb2);
    $recorder->record($crumb1);
    $recorder->record($crumb2);

    expect($recorder->glows())->toHaveCount(GlowRecorder::GLOW_LIMIT);

    $this->assertSame([
        $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1,
        $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1,
        $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb2, $crumb1, $crumb2,
    ], $recorder->glows());
});
