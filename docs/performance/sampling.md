---
title: Sampling 
---


Flare will sample traces based upon a sampling rate. Only a certain percentage of traces will be sent to Flare. The default sampling rate is 10%, which means that of all traces, 10% will be sent to Flare.

It is possible to change the sampling rate by calling the `sampleRate` method on the Flare config:

```php
$config->sampleRate(0.5); // 50% of all traces will be sent to Flare
```

If you always want to sample, you can set the sample rate to 1.0:

```php
$config->sampleRate(1.0); // 100% of all traces will be sent to Flare
```

By default, Flare uses the `RateSampler`, but creating your own sampler is possible. You can do this by implementing the `Sampler` interface, which should return a boolean value indicating whether the trace should be sampled or not:

```php
use Spatie\FlareClient\Sampling\Sampler;

class AlwaysSampler implements Sampler
{
    public function __construct(protected array $config) {}

    public function shouldSample(array $context): bool
    {
        return true
    }
}
```

The sampler then can be registered in the Flare config as such:

```php
$config->sampler(AlwaysSampler::class, $config);
```

It is possible to pass a config array to the sampler, which will be injected into the sampler's constructor. 