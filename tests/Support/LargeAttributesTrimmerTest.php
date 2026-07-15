<?php

use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\LargeAttributesTrimmer;

it('drops string and array attributes exceeding the budget', function () {
    $spanEvent = new SpanEvent('Event', 0, [
        'big_string' => str_repeat('a', 1025),
        'exact_string' => str_repeat('b', 1024),
        'big_nested_array' => ['nested' => str_repeat('c', 2000)],
        'big_array_by_item_count' => array_fill(0, 300, 1),
        'number' => PHP_INT_MAX,
        'small_string' => 'kept',
    ]);

    (new LargeAttributesTrimmer())->trim($spanEvent, 1);

    expect($spanEvent->attributes)->toBe([
        'exact_string' => str_repeat('b', 1024),
        'number' => PHP_INT_MAX,
        'small_string' => 'kept',
    ]);
    expect($spanEvent->droppedAttributesCount)->toBe(3);
});

it('does not trim when the budget is disabled', function () {
    $spanEvent = new SpanEvent('Event', 0, [
        'big_string' => str_repeat('a', 5000),
    ]);

    (new LargeAttributesTrimmer())->trim($spanEvent, 0);

    expect($spanEvent->attributes)->toHaveKey('big_string');
    expect($spanEvent->droppedAttributesCount)->toBe(0);
});
