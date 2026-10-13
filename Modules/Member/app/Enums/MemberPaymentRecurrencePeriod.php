<?php
namespace Modules\Member\Enums;

enum MemberPaymentRecurrencePeriod: string
{
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case HALF_YEARLY = 'half_yearly';
    case YEARLY = 'yearly';
}
