<?php

namespace Spatie\FlareClient\Tests\Time;

use DateTimeImmutable;
use Spatie\FlareClient\Time\TimeHelper;

it('will convert everything to nanoseconds', function () {
    expect(TimeHelper::minutes(1))->toBe(60 * 1000 * 1000 * 1000);
    expect(TimeHelper::seconds(1))->toBe(1000 * 1000 * 1000);
    expect(TimeHelper::milliseconds(1))->toBe(1000 * 1000);
    expect(TimeHelper::microseconds(1))->toBe(1000);
});

it('can convert php microsecond time to nanoseconds', function () {
    expect(TimeHelper::phpMicroTime(1723544548.2467))->toBe(1723544548246700032);
    expect(TimeHelper::phpMicroTime(1723544548.246))->toBe(1723544548246000128);
    expect(TimeHelper::phpMicroTime(1723544548.24))->toBe(1723544548240000000);
});
