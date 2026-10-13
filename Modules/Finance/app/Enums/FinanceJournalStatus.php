<?php

namespace Modules\Finance\Enums;

enum FinanceJournalStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
}
