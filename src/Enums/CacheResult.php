<?php

namespace Spatie\FlareClient\Enums;

enum CacheResult: string
{
    case Hit = 'hit';
    case Miss = 'miss';
    case Success = 'success';
    case Failure = 'failure';
}
