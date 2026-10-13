<?php
namespace Modules\Member\Enums;

enum MemberPaymentObligationStatus: string
{
    case PENDING = 'pending';
    case PARTIALLY_PAID = 'partially_paid';
    case PAID = 'paid';
    case WAIVED = 'waived';
    case CANCELLED = 'cancelled';
}
