# Dynamic Entry Point Sampling

## Context

Currently, sampling in Flare is a single global rate (e.g., 10% of all requests). There's no way to say "sample `/admin*` at 100%, `/api/health` at 0%, and everything else at 10%". The `samplerContext` array is plumbed through the system but never used by `RateSampler`.

This change adds per-entry-point sampling rules, early entry point detection, entry point info on logs, and a `Sample` middleware for route-level overrides. **Breaking change**: `Sampler` interface gets a new `dynamicRules()` method.

---

## Part 1: flare-client-php

### 1.1 New `DynamicSamplingRule` class
**File**: `src/Sampling/DynamicSamplingRule.php`

```php
class DynamicSamplingRule
{
    protected function __construct(
        protected readonly ?string $contextKey,
        protected readonly ?string $pattern,
        protected readonly float|Closure $rate,
    ) {}

    public static function forUrl(string $pattern, float|Closure $rate): static;
    public static function forCommand(string $pattern, float|Closure $rate): static;
    public static function forJob(string $pattern, float|Closure $rate): static;
    public static function using(Closure $callback): static;

    public function matches(array $context): bool;      // fnmatch against contextKey
    public function shouldSample(array $context): bool;  // rate check or closure call
}
```

Context keys used: `entry_point_url`, `entry_point_command`, `entry_point_job`. The `using()` closure receives the full context and returns `float|bool`.

Constructor is `protected` so Laravel package can extend with `forRoute`, `forJobQueue`, `forJobConnection`.

### 1.2 Update `Sampler` interface (BREAKING)
**File**: `src/Sampling/Sampler.php`

```php
interface Sampler
{
    public function shouldSample(array $context): bool;

    /** @return array<DynamicSamplingRule> */
    public function dynamicRules(): array;
}
```

### 1.3 Update existing samplers
- **`RateSampler`** (`src/Sampling/RateSampler.php`): Add `dynamicRules()` returning `$this->rules` from config key `dynamic_rules` (defaults to `[]`).
- **`AlwaysSampler`** (`src/Sampling/AlwaysSampler.php`): Return `[]`.
- **`NeverSampler`** (`src/Sampling/NeverSampler.php`): Return `[]`.

### 1.4 Update `Tracer` sampling flow
**File**: `src/Tracer.php`

In `startTrace()`, before the existing `$this->sampler->shouldSample()` call, iterate dynamic rules:

```php
foreach ($this->sampler->dynamicRules() as $rule) {
    if ($rule->matches($samplerContext)) {
        return $this->sampling = $rule->shouldSample($samplerContext);
    }
}
return $this->sampling = $this->sampler->shouldSample($samplerContext);
```

Add entry point tracking on Tracer:
- `protected ?string $currentEntryPointType = null`
- `protected ?string $currentEntryPointValue = null`
- `setEntryPoint(string $type, string $value): void`
- `currentEntryPointType(): ?string` / `currentEntryPointValue(): ?string`
- Reset in `endTrace()` and `unsample()`

Add `forceSample(): bool` for the `Sample` middleware to upgrade a non-sampled request to sampled.

### 1.5 Update `Logger` to include entry point attributes
**File**: `src/Logger.php`

In `log()`, after building the record, add entry point info from tracer:

```php
if ($type = $this->tracer->currentEntryPointType()) {
    $attributes['flare.entry_point.type'] = $type;
    $attributes['flare.entry_point.value'] = $this->tracer->currentEntryPointValue();
}
```

### 1.6 Update `FlareConfig`
**File**: `src/FlareConfig.php`

Add property and method for dynamic rules:
- `public array $samplingRules = []`
- `addSamplingRule(DynamicSamplingRule $rule): static`
- `samplingRules(DynamicSamplingRule ...$rules): static`

### 1.7 Update `FlareProvider`
**File**: `src/FlareProvider.php`

When creating the sampler singleton, merge `$this->config->samplingRules` into the sampler config as `dynamic_rules`.

### 1.8 Tests
**File**: `tests/Sampling/DynamicSamplingRuleTest.php`
- Test `forUrl` matching with fnmatch patterns
- Test `forCommand` matching
- Test `forJob` matching
- Test `using()` with closure returning float and bool
- Test that non-matching rules are skipped
- Test rate 0.0 (never) and 1.0 (always)

**File**: `tests/Sampling/RateSamplerTest.php`
- Add test for `dynamicRules()` returning configured rules
- Test that dynamic rules take precedence over default rate

**File**: `tests/Tracer/TracerSamplingTest.php` (or add to existing tracer tests)
- Test that dynamic rules are checked before default sampling
- Test `setEntryPoint` / `currentEntryPointType` / `currentEntryPointValue`
- Test `forceSample()` enables sampling
- Test entry point reset on `endTrace()` and `unsample()`

**File**: `tests/Logger/LoggerEntryPointTest.php`
- Test that logs include `flare.entry_point.type` and `flare.entry_point.value` when set

---

## Part 2: laravel-flare

### 2.1 `LaravelDynamicSamplingRule` extending `DynamicSamplingRule`
**File**: `src/Sampling/LaravelDynamicSamplingRule.php`

```php
class LaravelDynamicSamplingRule extends DynamicSamplingRule
{
    public static function forRoute(string $pattern, float|Closure $rate): static;
    public static function forJobQueue(string $pattern, float|Closure $rate): static;
    public static function forJobConnection(string $pattern, float|Closure $rate): static;
}
```

Context keys: `entry_point_route`, `entry_point_job_queue`, `entry_point_job_connection`.

### 2.2 Config additions
**File**: `config/flare.php`

Add `sample_rates` section (commented out by default):

```php
'sample_rates' => [
    // 'web' => [
    //     '/admin*' => 1.0,
    //     '/api/health' => 0,
    // ],
    // 'cli' => [
    //     'inspire' => 0,
    // ],
    // 'queue' => [
    //     App\Jobs\ProcessPodcast::class => 1.0,
    // ],
],
```

### 2.3 Parse config into DynamicSamplingRule objects
**File**: `src/FlareConfig.php`

In `fromLaravelConfig()`, parse `sample_rates` config into `DynamicSamplingRule` / `LaravelDynamicSamplingRule` objects and set them on the config via `samplingRules()`.

### 2.4 Early entry point detection in FlareServiceProvider
**File**: `src/FlareServiceProvider.php`

In `boot()`, before calling `$lifecycle->start()`, detect entry point and pass as `samplerContext`:

```php
$samplerContext = $this->detectEntryPoint();
$lifecycle->start(
    timeUnixNano: $startTimeUnixNano,
    traceparent: ...,
    samplerContext: $samplerContext,
);
```

Detection logic (lightweight, no framework overhead):
- **Web**: `$_SERVER['REQUEST_URI']` parsed to path -> `['entry_point_type' => 'web', 'entry_point_url' => $path]`
- **CLI**: `$_SERVER['argv'][1] ?? null` -> `['entry_point_type' => 'cli', 'entry_point_command' => $command]`

### 2.5 Set entry point on Tracer
**File**: `src/FlareServiceProvider.php` and recorders

After lifecycle start, set entry point on tracer so logs pick it up:
- Web: in `FlareServiceProvider::boot()` after `$lifecycle->start()`
- CLI: in `CommandRecorder::recordCommandStarting()` after detecting command
- Queue: in `JobRecorder::recordProcessing()` after detecting job class

### 2.6 Route-matched re-evaluation
**File**: `src/Http/Middleware/FlareTracingMiddleware.php`

After request is available in middleware, if route is matched, add `entry_point_route` to context and check if any `forRoute` rules apply. If the decision needs to change:
- Sampled -> should not be: call `$tracer->unsample()`
- Not sampled -> should be: call `$tracer->forceSample()`

### 2.7 Job entry point context
**File**: `src/Recorders/JobRecorder/JobRecorder.php`

In `recordProcessing()`, extract job class, queue, and connection from the event/payload and pass as `samplerContext` to `$lifecycle->startSubtask()`:

```php
$samplerContext = [
    'entry_point_type' => 'queue',
    'entry_point_job' => $jobClass,
    'entry_point_job_queue' => $event->job->getQueue(),
    'entry_point_job_connection' => $event->connectionName,
];
$this->lifecycle->startSubtask(traceparent: $traceparent, samplerContext: $samplerContext);
```

### 2.8 `Sample` middleware
**File**: `src/Http/Middleware/Sample.php`

Route-level sampling override (Nightwatch-style):

```php
class Sample
{
    public static function rate(float $rate): string;
    public static function always(): string;
    public static function never(): string;

    public function handle(Request $request, Closure $next, float $rate): Response;
}
```

Uses `Tracer::forceSample()` to upgrade and `Tracer::unsample()` to downgrade.

### 2.9 Tests
- `tests/Sampling/LaravelDynamicSamplingRuleTest.php`: Test `forRoute`, `forJobQueue`, `forJobConnection`
- `tests/Sampling/EntryPointSamplingTest.php`: Integration test with config-based rules for web/cli/queue
- `tests/Http/Middleware/SampleMiddlewareTest.php`: Test `Sample` middleware rate/always/never
- `tests/EntryPointDetectionTest.php`: Test early detection from `$_SERVER`

### 2.10 Documentation
**File**: `docs/dynamic-sampling.md` in laravel-flare repo

Draft documentation covering:
- Concept: what is dynamic sampling and why
- Config-based rules (sample_rates in config/flare.php)
- Programmatic rules via FlareConfig API
- Closure-based rules
- Sample middleware for per-route overrides
- Entry point detection (how web/cli/queue are detected)
- Examples for common scenarios

---

## Sampler Context Keys Reference

| Key | Type | Set by | Available for |
|-----|------|--------|---------------|
| `entry_point_type` | `web\|cli\|queue` | Service provider / recorders | All rules |
| `entry_point_url` | URL path | Service provider (web) | `forUrl()` |
| `entry_point_route` | Route pattern | FlareTracingMiddleware (Laravel) | `forRoute()` |
| `entry_point_command` | Command name | Service provider / CommandRecorder (cli) | `forCommand()` |
| `entry_point_job` | Job class | JobRecorder (queue) | `forJob()` |
| `entry_point_job_queue` | Queue name | JobRecorder (Laravel) | `forJobQueue()` |
| `entry_point_job_connection` | Connection | JobRecorder (Laravel) | `forJobConnection()` |

---

## Verification

1. **Unit tests**: `composer test` in both repos
2. **Static analysis**: `composer analyse` in both repos
3. **Code style**: `composer format` in both repos
4. **Manual test scenarios**:
   - Web request to a URL matching a `forUrl` rule -> verify correct sampling rate
   - CLI command matching a `forCommand` rule -> verify correct sampling
   - Queue job matching a `forJob` rule -> verify correct sampling
   - `Sample::rate(0.5)` middleware on a route -> verify override works
   - Logs include `flare.entry_point.type` and `flare.entry_point.value` attributes
   - No matching rule -> falls back to default sampler rate
   - Closure rule returning different rates based on context
