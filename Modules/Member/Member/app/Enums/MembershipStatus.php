<?php

namespace Modules\Member\Enums;

enum MembershipStatus: string
{
    case IN_REVIEW = 'in_review';
    case ACTIVE = 'active';
    case DORMANT = 'dormant';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
}