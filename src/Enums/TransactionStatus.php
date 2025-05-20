<?php

namespace Spatie\FlareClient\Enums;

enum TransactionStatus: string
{
    case Committed = 'committed';
    case RolledBack = 'rolled_back';
}
