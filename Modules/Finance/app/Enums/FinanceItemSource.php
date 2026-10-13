<?php

namespace Modules\Finance\Enums;

enum FinanceItemSource: string
{
    case MANUAL = 'manual';
    case MODULE = 'module';
}
