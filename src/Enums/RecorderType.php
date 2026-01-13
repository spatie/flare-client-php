<?php

namespace Spatie\FlareClient\Enums;

enum RecorderType: string
{
    case Application = 'application';
    case Cache = 'cache';
    case Command = 'command';
    case Context = 'context';
    case Dump = 'dump';
    case Event = 'event';
    case Filesystem = 'filesystem';
    case Glow = 'glow';
    case ExternalHttp = 'external_http';
    case Job = 'job';
    case Notification = 'notification';
    case Null = 'null';
    case Query = 'query';
    case Queue = 'queue';
    case Request = 'request';
    case RedisCommand = 'redis_command';
    case Routing = 'routing';
    case Response = 'response';
    case Transaction = 'transaction';
    case View = 'view';
}
