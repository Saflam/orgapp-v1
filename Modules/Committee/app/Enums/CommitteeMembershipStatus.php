<?php

namespace Modules\Committee\Enums;

enum CommitteeMembershipStatus: string
{
    case ACTIVE = 'active';
    case ENDED = 'ended';
    case CANCELLED = 'cancelled';
}