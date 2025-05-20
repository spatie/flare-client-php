<?php

namespace Spatie\FlareClient\Enums;

enum CacheOperation: string
{
    case Get = 'get';
    case Set = 'set';
    case Forget = 'forget';
}
