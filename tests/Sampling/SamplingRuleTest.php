<?php

use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\Sampling\SamplingRule;
use Spatie\FlareClient\Sampling\SamplingRuleType;

it('throws when creating a rule from an invalid array', function (array $data, string $message) {
    expect(fn () => SamplingRule::fromArray($data))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'non-enum type' => [
        ['type' => 'route', 'pattern' => 'GET /api/*', 'rate' => 0.5],
        'Sampling rule "type" must be a SamplingRuleType enum.',
    ],
    'closure type' => [
        ['type' => SamplingRuleType::Closure, 'pattern' => 'anything', 'rate' => 1.0],
        'Closure sampling rules cannot be created from arrays.',
    ],
    'missing keys' => [
        ['rate' => 1.0],
        'Sampling rule array must contain "type", "pattern" and "rate" keys.',
    ],
    'rate above 1' => [
        ['type' => SamplingRuleType::Url, 'pattern' => '/api/*', 'rate' => 1.5],
        'Sampling rate must be between 0 and 1.',
    ],
    'rate below 0' => [
        ['type' => SamplingRuleType::Url, 'pattern' => '/api/*', 'rate' => -0.1],
        'Sampling rate must be between 0 and 1.',
    ],
]);

it('throws when constructing a static rule with an out-of-range rate', function (Closure $factory) {
    expect(fn () => $factory())->toThrow(InvalidArgumentException::class, 'Sampling rate must be between 0 and 1.');
})->with([
    'forUrl above 1' => [fn () => SamplingRule::forUrl('https://example.com/*', 1.5)],
    'forPath below 0' => [fn () => SamplingRule::forPath('/api/*', -0.1)],
    'forRoute below 0' => [fn () => SamplingRule::forRoute('api.show', -0.5)],
    'forCommand above 1' => [fn () => SamplingRule::forCommand('migrate', 2.0)],
    'forJob below 0' => [fn () => SamplingRule::forJob('App\\Jobs\\*', -0.01)],
]);

it('always allows url, job, and early closure rules to run', function (SamplingRule $rule) {
    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/test');

    expect($rule->canRun($entryPoint))->toBeTrue();
})->with([
    'url' => fn () => SamplingRule::forUrl('https://example.com/*', 1.0),
    'path' => fn () => SamplingRule::forPath('/admin/*', 1.0),
    'job' => fn () => SamplingRule::forJob('App\\Jobs\\*', 0.5),
    'early closure' => fn () => SamplingRule::usingEarly(fn () => 1.0),
]);

it('only allows route, command, and closure rules to run when the handler is resolved', function (SamplingRule $rule) {
    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/test');

    expect($rule->canRun($entryPoint))->toBeFalse();

    $entryPoint->setHandler('GET /test', 'TestController', 'php_request');

    expect($rule->canRun($entryPoint))->toBeTrue();
})->with([
    'route' => fn () => SamplingRule::forRoute('/api/*', 0.5),
    'command' => fn () => SamplingRule::forCommand('migrate', 0),
    'closure' => fn () => SamplingRule::using(fn () => 1.0),
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
    'early closure receives entry point without handler' => fn () => [
        SamplingRule::usingEarly(fn (EntryPoint $ep) => $ep->type === EntryPointType::Web ? 0.5 : null),
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

it('builds an equivalent rule from an array', function () {
    $rule = SamplingRule::fromArray([
        'type' => SamplingRuleType::Path,
        'pattern' => '/admin/*',
        'rate' => 0.5,
    ]);

    expect($rule->type())->toBe(SamplingRuleType::Path);

    $matching = new EntryPoint(EntryPointType::Web, 'https://example.com/admin/users');
    $other = new EntryPoint(EntryPointType::Web, 'https://example.com/public/page');

    expect($rule->getMatchedRate($matching))->toBe(0.5);
    expect($rule->getMatchedRate($other))->toBeNull();
});
