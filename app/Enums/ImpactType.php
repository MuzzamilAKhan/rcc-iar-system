<?php

namespace App\Enums;

enum ImpactType: string
{
    case NoImpact = 'no_impact';
    case ReRegistrationRequired = 're_registration_required';
    case NotificationRequired = 'notification_required';
}
