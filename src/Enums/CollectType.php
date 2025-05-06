<?php

namespace Spatie\FlareClient\Enums;

use Spatie\FlareClient\Contracts\FlareCollectType;

enum CollectType: string implements FlareCollectType
{
    case Dumps = 'dumps';
    case Requests = 'requests';
    case Commands = 'console';
    case GitInfo = 'git_info';
    case Cache = 'cache';
    case Glows = 'glows';
    case Logs = 'logs';
    case Solutions = 'solutions';
    case Throwables = 'throwables';
    case Queries = 'queries';
    case Transactions = 'database_transactions';
    case ServerInfo = 'server_info';
    case Jobs = 'jobs';
    case Filesystem = 'filesystem';
    case ExternalHttp = 'external_http';
    case RedisCommands = 'redis_commands';
    case Notifications = 'notifications';
    case Views = 'views';
    case StackFrameArguments = 'stack_frame_arguments';
}
