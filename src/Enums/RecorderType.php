<?php

namespace Spatie\FlareClient\Enums;

enum RecorderType: string
{
    case Cache = 'cache';
    case Dump = 'dump';
    case Exception = 'exception';
    case Glow = 'glow';
    case Log = 'log';
    case Query = 'query';
    case Transaction = 'transaction';
    case Null = 'null';
    case Event = 'event';
    case Job = 'job';
    case RedisCommand = 'redis_command';
}
