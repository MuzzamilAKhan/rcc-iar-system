<?php

namespace App\Enums;

enum IarStatus: string
{
    case Draft = 'draft';
    case NeedsAttention = 'needs_attention';
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Closed = 'closed';
}
