<?php
namespace Modules\Member\Enums;

enum MemberPaymentRecurrenceBy: string
{
    case JOIN_DATE = 'join_date';
    case SPECIFIC_DATE = 'specific_date';
}
