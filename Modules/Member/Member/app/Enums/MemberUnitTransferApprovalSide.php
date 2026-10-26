<?php

namespace Modules\Member\Enums;

enum MemberUnitTransferApprovalSide: string
{
    case OUTGOING = 'outgoing';
    case INCOMING = 'incoming';
}
