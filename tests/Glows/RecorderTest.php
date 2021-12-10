<?php

use Spatie\FlareClient\Glows\Glow;
use Spatie\FlareClient\Glows\GlowRecorder;
use Spatie\FlareClient\Tests\TestCase;

uses(TestCase::class);

it('is initially empty', function () {
    $recorder = new GlowRecorder();

    $this->assertCount(0, $recorder->glows());
});

it('stores glows', function () {
    $recorder = new GlowRecorder();

    $glow = new Glow('Some name', 'info', [
        'some' => 'metadata',
    ]);

    $recorder->record($glow);

    $this->assertCount(1, $recorder->glows());

    $this->assertSame($glow, $recorder->glows()[0]);
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

    $this->assertCount(GlowRecorder::GLOW_LIMIT, $recorder->glows());

    $this->assertSame([
        $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1,
        $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1,
        $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb1, $crumb2, $crumb1, $crumb2,
    ], $recorder->glows());
});
