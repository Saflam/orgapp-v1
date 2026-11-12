<?php

namespace Modules\Committee\Enums;

enum TrusteeAppointmentStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case REMOVED = 'removed';
}
