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
]);

it('always allows url, job, and early closure rules to run', function (SamplingRule $rule) {
    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/test');

    expect($rule->canRun($entryPoint))->toBeTrue();
})->with([
    'url' => fn () => SamplingRule::forUrl('/admin/*', 1.0),
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
    'url wildcard match' => fn () => [
        SamplingRule::forUrl('/admin/*', 1.0),
        new EntryPoint(EntryPointType::Web, 'https://example.com/admin/users'),
        1.0,
    ],
    'url exact match' => fn () => [
        SamplingRule::forUrl('/api/health', 0.25),
        new EntryPoint(EntryPointType::Web, 'https://example.com/api/health'),
        0.25,
    ],
    'url no match' => fn () => [
        SamplingRule::forUrl('/admin/*', 1.0),
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
]);
