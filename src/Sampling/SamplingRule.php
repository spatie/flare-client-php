<?php

namespace Spatie\FlareClient\Sampling;

use Closure;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\Sampling\Rules\ClosureSamplingRule;
use Spatie\FlareClient\Sampling\Rules\CommandSamplingRule;
use Spatie\FlareClient\Sampling\Rules\DeferredClosureSamplingRule;
use Spatie\FlareClient\Sampling\Rules\JobSamplingRule;
use Spatie\FlareClient\Sampling\Rules\PathSamplingRule;
use Spatie\FlareClient\Sampling\Rules\RouteSamplingRule;
use Spatie\FlareClient\Sampling\Rules\UrlSamplingRule;

abstract class SamplingRule
{
    public static function forUrl(string $pattern, float $rate): UrlSamplingRule
    {
        return new UrlSamplingRule($pattern, $rate);
    }

    public static function forPath(string $pattern, float $rate): PathSamplingRule
    {
        return new PathSamplingRule($pattern, $rate);
    }

    public static function forRoute(string $pattern, float $rate): RouteSamplingRule
    {
        return new RouteSamplingRule($pattern, $rate);
    }

    public static function forCommand(string $pattern, float $rate): CommandSamplingRule
    {
        return new CommandSamplingRule($pattern, $rate);
    }

    public static function forJob(string $pattern, float $rate): JobSamplingRule
    {
        return new JobSamplingRule($pattern, $rate);
    }

    /** @param Closure(EntryPoint):?float $closure */
    public static function using(Closure $closure): ClosureSamplingRule
    {
        return new ClosureSamplingRule($closure);
    }

    /** @param Closure(EntryPoint):?float $closure */
    public static function usingDeferred(Closure $closure): DeferredClosureSamplingRule
    {
        return new DeferredClosureSamplingRule($closure);
    }

    abstract public function appliesTo(EntryPointType $entryPointType): bool;

    abstract public function getMatchedRate(EntryPoint $entryPoint): ?float;
}
