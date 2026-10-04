<?php

namespace Modules\Committee\Enums;

enum CommitteeKind: string
{
    case RECURRING = 'recurring';
    case TEMPORARY = 'temporary';
}