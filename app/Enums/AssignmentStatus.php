<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Assigned = 'assigned';
    case Opened = 'opened';
    case Draft = 'draft';
    case Submitted = 'submitted';
}
