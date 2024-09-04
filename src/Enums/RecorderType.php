<?php

namespace Spatie\FlareClient\Enums;

enum RecorderType: string
{
    case Application = 'application';
    case Cache = 'cache';
    case Command = 'command';
    case Dump = 'dump';
    case Event = 'event';
    case Exception = 'exception';
    case Filesystem = 'filesystem';
    case Glow = 'glow';
    case Http = 'http';
    case Job = 'job';
    case Log = 'log';
    case Notification = 'notification';
    case Null = 'null';
    case Query = 'query';
    case Queue = 'queue';
    case RedisCommand = 'redis_command';
    case Routing = 'routing';
    case Transaction = 'transaction';
    case View = 'view';
}
