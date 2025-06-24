<?php

namespace Spatie\FlareClient\Disabled;

use Spatie\FlareClient\Recorders\ApplicationRecorder\ApplicationRecorder;
use Spatie\FlareClient\Spans\Span;

class DisabledApplicationRecorder extends ApplicationRecorder
{
    public function __construct()
    {
    }

    public function recordStart(
        array $attributes = [],
        ?int $time = null,
    ): ?Span {
        return null;
    }

    public function recordRegistering(
        array $attributes = [],
        ?int $time = null,
    ): ?Span {
        return null;
    }

    public function recordRegistered(
        array $attributes = [],
        ?int $time = null,
    ): ?Span {
        return null;
    }

    public function recordRegistration(
        array $attributes = [],
        ?int $start = null,
        ?int $end = null,
        ?int $duration = null,
    ): ?Span {
        return null;
    }

    public function recordBooting(
        array $attributes = [],
        ?int $time = null,
    ): ?Span {
        return null;
    }

    public function recordBooted(
        array $attributes = [],
        ?int $time = null,
    ): ?Span {
        return null;
    }

    public function recordBoot(
        array $attributes = [],
        ?int $start = null,
        ?int $end = null,
        ?int $duration = null,
    ): ?Span {
        return null;
    }

    public function recordTerminating(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        return null;
    }

    public function recordTerminated(
        array $attributes = [],
        ?int $time = null,
    ): ?Span {
        return null;
    }

    public function recordTermination(
        array $attributes = [],
        ?int $start = null,
        ?int $end = null,
        ?int $duration = null,
    ): ?Span {
        return null;
    }

    public function recordEnd(
        array $attributes = [],
        ?int $time = null,
    ): ?Span {
        return null;
    }
}
