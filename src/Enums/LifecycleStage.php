<?php

namespace Spatie\FlareClient\Enums;

enum LifecycleStage: string
{
    case Idle = 'idle';
    case Started = 'started';
    case Registering = 'registering';
    case Registered = 'registered';
    case Booting = 'booting';
    case Booted = 'booted';
    case Subtask = 'subtask';
    case Terminating = 'terminating';
    case Terminated = 'terminated';
}
