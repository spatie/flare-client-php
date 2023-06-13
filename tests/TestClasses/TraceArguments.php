<?php

namespace Spatie\FlareClient\Tests\TestClasses;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use Closure;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Spatie\Backtrace\Backtrace;
use Spatie\Backtrace\Frame;
use Symfony\Component\HttpFoundation\Request;

class TraceArguments
{
    public static function create(): self
    {
        return new self();
    }

    public function withoutArgumentsEnabledInTrace()
    {
        return Backtrace::create()->frames()[0];
    }

    public function withoutArguments(): Frame
    {
        return $this->getTraceFrame();
    }

    public function withSimpleArguments(
        bool $true,
        bool $false,
        string $emptyString,
        string $string,
        int $int,
        int $intMax,
        float $float,
        float $floatNan,
        float $floatInfinity,
        ?string $null,
        FakeUnitEnum $unitEnum,
        FakeStringBackedEnum $stringBackedEnum,
        FakeIntBackedEnum $intBackedEnum,
    ): Frame {
        return $this->getTraceFrame();
    }

    public function withArray(
        array $array,
    ) {
        return $this->getTraceFrame();
    }

    public function withDefaults(
        string $stringA,
        string $stringB = 'B',
    ): Frame {
        return $this->getTraceFrame();
    }

    public function withVariadicArgument(
        string $base,
        string ...$strings,
    ): Frame {
        return $this->getTraceFrame();
    }

    public function withDefaultAndVardiadicArgument(
        string $base = 'base',
        string ...$strings,
    ): Frame {
        return $this->getTraceFrame();
    }

    public function withClosure(
        Closure $closure
    ): Frame {
        return $this->getTraceFrame();
    }

    public function withDate(
        DateTime $dateTime,
        DateTimeImmutable $dateTimeImmutable,
        Carbon $carbon,
        CarbonImmutable $carbonImmutable,
    ): Frame {
        return $this->getTraceFrame();
    }

    public function withTimeZone(
        DateTimeZone $dateTimeZone,
        CarbonTimeZone $carbonTimeZone
    ): Frame {
        return $this->getTraceFrame();
    }

    public function withSymfonyRequest(Request $request): Frame
    {
        return $this->getTraceFrame();
    }

    public function exception(
        string $string,
        DateTime $dateTime,
    ): Exception {
        return new Exception('Some exception');
    }

    protected function getTraceFrame(): Frame
    {
        return Backtrace::create()->withArguments()->frames()[1];
    }
}
