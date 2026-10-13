<?php

namespace Modules\Finance\Enums;

enum FinanceCustomerType: string
{
    case MEMBER = 'member';
    case EXTERNAL = 'external';
}
