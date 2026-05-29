<?php

namespace App\Lib\BulkSignInOut;

enum ShiftAction: string
{
    case In = 'in';
    case Out = 'out';
    case InOut = 'inout';
    case Unknown = 'unknown';
}
