<?php

namespace Spatie\FlareClient\Recorders;

abstract class Recorder
{
    public const DEFAULT_WITH_TRACES = true;

    public const DEFAULT_WITH_ERRORS = true;

    public const DEFAULT_MAX_ITEMS_WITH_ERRORS = 100;

    public const DEFAULT_FIND_ORIGIN = false;

    public const DEFAULT_FIND_ORIGIN_THRESHOLD = null;
}
