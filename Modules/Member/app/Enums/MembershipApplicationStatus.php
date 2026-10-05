<?php

namespace Modules\Member\Enums;

enum MembershipApplicationStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case VERIFIED = 'verified';
    case REVIEWED = 'reviewed';
    case APPROVED = 'approved';
    case PAYMENT = 'payment';
    case CONFIRMED = 'confirmed';
    case REJECTED = 'rejected';
    case WITHDRAWN = 'withdrawn';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::CONFIRMED,
            self::WITHDRAWN,
        ], true);
    }
}
