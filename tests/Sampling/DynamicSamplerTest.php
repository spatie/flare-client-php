<?php

use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\Sampling\DynamicSampler;
use Spatie\FlareClient\Sampling\SamplingRule;
use Spatie\FlareClient\Sampling\SamplingRuleType;

it('behaves like rate sampler with no rules', function () {
    $sampler = new DynamicSampler(['base_rate' => 0]);
    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/test');

    expect($sampler->shouldSample($entryPoint))->toBeFalse();
    expect($sampler->isPending())->toBeFalse();
});

it('behaves like rate sampler with no rules and rate 1', function () {
    $sampler = new DynamicSampler(['base_rate' => 1]);
    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/test');

    expect($sampler->shouldSample($entryPoint))->toBeTrue();
    expect($sampler->isPending())->toBeFalse();
});

it('skips all rules when entry point type does not match any rule', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 0,
        'rules' => [
            SamplingRule::forPath('/admin/*', 1.0),
            SamplingRule::forRoute('/api/*', 1.0),
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Cli, 'artisan migrate');

    expect($sampler->shouldSample($entryPoint))->toBeFalse();
    expect($sampler->isPending())->toBeFalse();
});

it('evaluates url rule immediately with no pending state', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 0,
        'rules' => [
            SamplingRule::forPath('/admin/*', 1.0),
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/admin/users');

    expect($sampler->shouldSample($entryPoint))->toBeTrue();
    expect($sampler->isPending())->toBeFalse();
});


it('falls back to base rate when no rule matches', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 0,
        'rules' => [
            SamplingRule::forPath('/admin/*', 1.0),
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/public/page');

    expect($sampler->shouldSample($entryPoint))->toBeFalse();
    expect($sampler->isPending())->toBeFalse();
});

it('sets pending when a derrable rule cannot be evaluated', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 0,
        'rules' => [
            SamplingRule::forRoute('/admin/*', 1.0),
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/admin/users');

    expect($sampler->shouldSample($entryPoint))->toBeTrue();
    expect($sampler->isPending())->toBeTrue();
});

it('reevaluates when handler becomes available and rule matches', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 0,
        'rules' => [
            SamplingRule::forRoute('/admin/*', 1.0),
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/admin/users');

    $sampler->shouldSample($entryPoint);

    $entryPoint->setHandler('GET /admin/users', 'AdminController', 'php_request');

    expect($sampler->reevaluate($entryPoint))->toBeTrue();
    expect($sampler->isPending())->toBeFalse();
});

it('reevaluates to false when route rule has rate 0', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 1,
        'rules' => [
            SamplingRule::forRoute('/api/health', 0),
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/api/health');

    expect($sampler->shouldSample($entryPoint))->toBeTrue();
    expect($sampler->isPending())->toBeTrue();

    $entryPoint->setHandler('GET /api/health', 'HealthController', 'php_request');

    expect($sampler->reevaluate($entryPoint))->toBeFalse();
});

it('reevaluates to base rate when no rule matches after handler resolution', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 0,
        'rules' => [
            SamplingRule::forRoute('/admin/*', 1.0),
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/public/page');

    $sampler->shouldSample($entryPoint);

    $entryPoint->setHandler('GET /public/page', 'PageController', 'php_request');

    expect($sampler->reevaluate($entryPoint))->toBeFalse();
});

it('respects priority: first matching rule wins', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 0,
        'rules' => [
            SamplingRule::forPath('/admin/*', 1.0),
            SamplingRule::forPath('/admin/secret', 0),
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/admin/secret');

    expect($sampler->shouldSample($entryPoint))->toBeTrue();
});

it('breaks on deferred rule and samples optimistically for later reevaluation', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 0,
        'rules' => [
            SamplingRule::forRoute('/admin/*', 1.0),
            SamplingRule::forPath('/api/*', 0),
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/api/health');

    expect($sampler->shouldSample($entryPoint))->toBeTrue();
    expect($sampler->isPending())->toBeTrue();
});

it('reevaluates correctly after breaking on deferred rule', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 0,
        'rules' => [
            SamplingRule::forRoute('/admin/*', 1.0),
            SamplingRule::forPath('/api/*', 0),
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/api/health');

    $sampler->shouldSample($entryPoint);

    $entryPoint->setHandler('GET /api/health', 'HealthController', 'php_request');

    expect($sampler->reevaluate($entryPoint))->toBeFalse();
});

it('command rule sets pending when handler not resolved', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 0,
        'rules' => [
            SamplingRule::forCommand('schedule:*', 0),
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Cli, 'artisan schedule:run');

    expect($sampler->shouldSample($entryPoint))->toBeTrue();
    expect($sampler->isPending())->toBeTrue();
});

it('job rule evaluates immediately', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 0,
        'rules' => [
            SamplingRule::forJob('App\\Jobs\\ProcessPodcast', 1.0),
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Queue, 'App\\Jobs\\ProcessPodcast');
    $entryPoint->setHandler('App\\Jobs\\ProcessPodcast', 'App\\Jobs\\ProcessPodcast', 'php_job');

    expect($sampler->shouldSample($entryPoint))->toBeTrue();
    expect($sampler->isPending())->toBeFalse();
});

it('reset clears pending state', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 0,
        'rules' => [
            SamplingRule::forRoute('/admin/*', 1.0),
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/admin/users');

    $sampler->shouldSample($entryPoint);

    expect($sampler->isPending())->toBeTrue();

    $sampler->reset();

    expect($sampler->isPending())->toBeFalse();
});

it('accepts array-defined rules in config', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 0,
        'rules' => [
            ['type' => SamplingRuleType::Path, 'pattern' => '/admin/*', 'rate' => 1.0],
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/admin/users');

    expect($sampler->shouldSample($entryPoint))->toBeTrue();
});

it('defers a closure rule until the handler is resolved', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 0,
        'rules' => [
            SamplingRule::using(fn (EntryPoint $ep) => str_contains($ep->handlerIdentifier, 'admin') ? 1.0 : null),
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/admin/users');

    expect($sampler->shouldSample($entryPoint))->toBeTrue();
    expect($sampler->isPending())->toBeTrue();

    $entryPoint->setHandler('GET /admin/users', 'AdminController', 'php_request');

    expect($sampler->reevaluate($entryPoint))->toBeTrue();
    expect($sampler->isPending())->toBeFalse();
});

it('runs an early closure without waiting for the handler', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 0,
        'rules' => [
            SamplingRule::usingEarly(fn (EntryPoint $ep) => $ep->type === EntryPointType::Web ? 1.0 : null),
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/whatever');

    expect($sampler->shouldSample($entryPoint))->toBeTrue();
    expect($sampler->isPending())->toBeFalse();
});

it('lets a non-deferrable rule before a deferrable one decide immediately', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 0,
        'rules' => [
            SamplingRule::forPath('/api/health', 1.0),
            SamplingRule::forRoute('/api/*', 0),
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/api/health');

    expect($sampler->shouldSample($entryPoint))->toBeTrue();
    expect($sampler->isPending())->toBeFalse();
});

it('reevaluates to the base rate when called without prior pending state', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 1,
        'rules' => [
            SamplingRule::forRoute('/admin/*', 0),
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/public');
    $entryPoint->setHandler('GET /public', 'PublicController', 'php_request');

    expect($sampler->reevaluate($entryPoint))->toBeTrue();
});

it('honors parentSampled true when no rule matches and base rate is zero', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 0,
        'rules' => [
            SamplingRule::forPath('/admin/*', 1.0),
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/public');

    expect($sampler->shouldSample($entryPoint, parentSampled: true))->toBeTrue();
});

it('honors parentSampled false when no rule matches and base rate is one', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 1,
        'rules' => [
            SamplingRule::forPath('/admin/*', 1.0),
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/public');

    expect($sampler->shouldSample($entryPoint, parentSampled: false))->toBeFalse();
});

it('lets a matching rule override parentSampled', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 0,
        'rules' => [
            SamplingRule::forPath('/admin/*', 1.0),
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/admin/users');

    expect($sampler->shouldSample($entryPoint, parentSampled: false))->toBeTrue();
});

it('lets a matching rule with rate zero override parentSampled true', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 0,
        'rules' => [
            SamplingRule::forPath('/admin/*', 0),
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/admin/users');

    expect($sampler->shouldSample($entryPoint, parentSampled: true))->toBeFalse();
});

it('falls back to stored parentSampled on reevaluate when no rule matches', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 0,
        'rules' => [
            SamplingRule::forRoute('/admin/*', 1.0),
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/public');

    $sampler->shouldSample($entryPoint, parentSampled: true);

    $entryPoint->setHandler('GET /public', 'PublicController', 'php_request');

    expect($sampler->reevaluate($entryPoint))->toBeTrue();
});

it('reset clears stored parentSampled so reevaluate falls back to base rate', function () {
    $sampler = new DynamicSampler([
        'base_rate' => 0,
        'rules' => [
            SamplingRule::forRoute('/admin/*', 1.0),
        ],
    ]);

    $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/public');

    $sampler->shouldSample($entryPoint, parentSampled: true);
    $sampler->reset();

    $entryPoint->setHandler('GET /public', 'PublicController', 'php_request');

    expect($sampler->reevaluate($entryPoint))->toBeFalse();
});
