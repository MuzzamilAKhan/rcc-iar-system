<?php

namespace App\Enums;

enum ExceptionStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
}
