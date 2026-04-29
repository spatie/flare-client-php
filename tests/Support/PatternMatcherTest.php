<?php

use Spatie\FlareClient\Support\PatternMatcher;

it('matches an exact pattern', function () {
    expect(PatternMatcher::matches('schedule:run', 'schedule:run'))->toBeTrue();
});

it('does not match when value differs from pattern', function () {
    expect(PatternMatcher::matches('migrate', 'schedule:run'))->toBeFalse();
});

it('matches a wildcard prefix pattern', function () {
    expect(PatternMatcher::matches('make:migration', 'make:*'))->toBeTrue();
    expect(PatternMatcher::matches('make:controller', 'make:*'))->toBeTrue();
});

it('does not match when prefix differs', function () {
    expect(PatternMatcher::matches('migrate', 'make:*'))->toBeFalse();
});

it('matches a wildcard suffix pattern', function () {
    expect(PatternMatcher::matches('App\\Jobs\\SendEmail', '*\\SendEmail'))->toBeTrue();
});

it('matches a wildcard inside a pattern', function () {
    expect(PatternMatcher::matches('App\\Commands\\Internal\\Cleanup', 'App\\Commands\\*\\Cleanup'))->toBeTrue();
});

it('treats the pattern as anchored', function () {
    expect(PatternMatcher::matches('prefix-make:migration-suffix', 'make:migration'))->toBeFalse();
});

it('escapes regex metacharacters in patterns', function () {
    expect(PatternMatcher::matches('/api/v1.users', '/api/v1.users'))->toBeTrue();
    expect(PatternMatcher::matches('/api/v1Xusers', '/api/v1.users'))->toBeFalse();
});

it('returns false when matching against an empty pattern list', function () {
    expect(PatternMatcher::matchesAny('anything', []))->toBeFalse();
});

it('matches any when at least one pattern matches', function () {
    expect(PatternMatcher::matchesAny('make:migration', ['migrate', 'make:*', 'queue:work']))->toBeTrue();
});

it('does not match any when no pattern matches', function () {
    expect(PatternMatcher::matchesAny('serve', ['migrate', 'make:*', 'queue:work']))->toBeFalse();
});
