<?php

namespace Modules\Member\Enums;

enum MemberUnitTransferApprovalStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
