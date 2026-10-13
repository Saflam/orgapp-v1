<?php

namespace Modules\Finance\Enums;

enum FinancePeriodStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
}
