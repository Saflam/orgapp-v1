<?php

namespace Modules\Finance\Enums;

enum FinancePeriodStatus: string
{
    case DRAFT = 'draft';
    case OPEN = 'open';
    case CLOSED = 'closed';
}
