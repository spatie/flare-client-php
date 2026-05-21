<?php

use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\Sampling\DeferredSamplerRule;
use Spatie\FlareClient\Sampling\SamplingRule;

it('throws when constructing a static rule with an out-of-range rate', function (Closure $factory) {
    expect(fn () => $factory())->toThrow(InvalidArgumentException::class, 'Sampling rate must be between 0 and 1.');
})->with([
    'forUrl above 1' => [fn () => SamplingRule::forUrl('https://example.com/*', 1.5)],
    'forPath below 0' => [fn () => SamplingRule::forPath('/api/*', -0.1)],
    'forRoute below 0' => [fn () => SamplingRule::forRoute('api.show', -0.5)],
    'forCommand above 1' => [fn () => SamplingRule::forCommand('migrate', 2.0)],
    'forJob below 0' => [fn () => SamplingRule::forJob('App\\Jobs\\*', -0.01)],
]);

it('does not mark url, path, job, or immediate closure rules as deferred', function (SamplingRule $rule) {
    expect($rule)->not->toBeInstanceOf(DeferredSamplerRule::class);
})->with([
    'url' => fn () => SamplingRule::forUrl('https://example.com/*', 1.0),
    'path' => fn () => SamplingRule::forPath('/admin/*', 1.0),
    'job' => fn () => SamplingRule::forJob('App\\Jobs\\*', 0.5),
    'closure' => fn () => SamplingRule::using(fn () => 1.0),
]);

it('marks route, command, and deferred closure rules as deferred', function (SamplingRule $rule) {
    expect($rule)->toBeInstanceOf(DeferredSamplerRule::class);
})->with([
    'route' => fn () => SamplingRule::forRoute('/api/*', 0.5),
    'command' => fn () => SamplingRule::forCommand('migrate', 0),
    'deferred closure' => fn () => SamplingRule::usingDeferred(fn () => 1.0),
]);

it('returns the matched rate when the entry point matches', function (SamplingRule $rule, EntryPoint $entryPoint, ?float $expected) {
    expect($rule->getMatchedRate($entryPoint))->toBe($expected);
})->with([
    'url full URL wildcard match' => fn () => [
        SamplingRule::forUrl('https://example.com/admin/*', 1.0),
        new EntryPoint(EntryPointType::Web, 'https://example.com/admin/users'),
        1.0,
    ],
    'url full URL exact match' => fn () => [
        SamplingRule::forUrl('https://example.com/api/health', 0.25),
        new EntryPoint(EntryPointType::Web, 'https://example.com/api/health'),
        0.25,
    ],
    'url no match against a different host' => fn () => [
        SamplingRule::forUrl('https://other.com/*', 1.0),
        new EntryPoint(EntryPointType::Web, 'https://example.com/api/users'),
        null,
    ],
    'path wildcard match' => fn () => [
        SamplingRule::forPath('/admin/*', 1.0),
        new EntryPoint(EntryPointType::Web, 'https://example.com/admin/users'),
        1.0,
    ],
    'path exact match' => fn () => [
        SamplingRule::forPath('/api/health', 0.25),
        new EntryPoint(EntryPointType::Web, 'https://example.com/api/health'),
        0.25,
    ],
    'path no match' => fn () => [
        SamplingRule::forPath('/admin/*', 1.0),
        new EntryPoint(EntryPointType::Web, 'https://example.com/api/users'),
        null,
    ],
    'route match' => function () {
        $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/api/users');
        $entryPoint->setHandler('GET /api/users', 'UsersController', 'php_request');

        return [SamplingRule::forRoute('/api/*', 0.5), $entryPoint, 0.5];
    },
    'command exact match' => function () {
        $entryPoint = new EntryPoint(EntryPointType::Cli, 'artisan migrate');
        $entryPoint->setHandler('migrate', 'MigrateCommand', 'php_command');

        return [SamplingRule::forCommand('migrate', 0), $entryPoint, 0.0];
    },
    'command wildcard match' => function () {
        $entryPoint = new EntryPoint(EntryPointType::Cli, 'artisan schedule:run');
        $entryPoint->setHandler('schedule:run', 'ScheduleRunCommand', 'php_command');

        return [SamplingRule::forCommand('schedule:*', 0), $entryPoint, 0.0];
    },
    'job exact match' => function () {
        $entryPoint = new EntryPoint(EntryPointType::Queue, 'App\\Jobs\\ProcessPodcast');
        $entryPoint->setHandler('App\\Jobs\\ProcessPodcast', 'App\\Jobs\\ProcessPodcast', 'php_job');

        return [SamplingRule::forJob('App\\Jobs\\ProcessPodcast', 0.5), $entryPoint, 0.5];
    },
    'job wildcard match' => function () {
        $entryPoint = new EntryPoint(EntryPointType::Queue, 'App\\Jobs\\ProcessPodcast');
        $entryPoint->setHandler('App\\Jobs\\ProcessPodcast', 'App\\Jobs\\ProcessPodcast', 'php_job');

        return [SamplingRule::forJob('App\\Jobs\\*', 0.5), $entryPoint, 0.5];
    },
    'closure returns rate' => function () {
        $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/admin/users');
        $entryPoint->setHandler('GET /admin/users', 'AdminController', 'php_request');

        return [
            SamplingRule::using(fn (EntryPoint $ep) => str_contains($ep->handlerIdentifier, 'admin') ? 0.75 : null),
            $entryPoint,
            0.75,
        ];
    },
    'closure returns null' => function () {
        $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/page');
        $entryPoint->setHandler('GET /page', 'PageController', 'php_request');

        return [SamplingRule::using(fn (EntryPoint $ep) => null), $entryPoint, null];
    },
    'closure receives entry point without handler' => fn () => [
        SamplingRule::using(fn (EntryPoint $ep) => $ep->type === EntryPointType::Web ? 0.5 : null),
        new EntryPoint(EntryPointType::Web, 'https://example.com/page'),
        0.5,
    ],
    'path with no path component falls back to /' => fn () => [
        SamplingRule::forPath('/', 0.42),
        new EntryPoint(EntryPointType::Web, 'https://example.com'),
        0.42,
    ],
    'path pattern matches value with trailing slash' => fn () => [
        SamplingRule::forPath('/foo', 1.0),
        new EntryPoint(EntryPointType::Web, 'https://example.com/foo/'),
        1.0,
    ],
    'url pattern matches value with trailing slash' => fn () => [
        SamplingRule::forUrl('https://example.com/foo', 1.0),
        new EntryPoint(EntryPointType::Web, 'https://example.com/foo/'),
        1.0,
    ],
    'route handler without method prefix uses full identifier' => function () {
        $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/users');

        $entryPoint->setHandler('users.index', 'UsersController', 'php_request');

        return [SamplingRule::forRoute('users.index', 0.33), $entryPoint, 0.33];
    },
]);
