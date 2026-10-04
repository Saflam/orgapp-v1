<?php

namespace Modules\Committee\Enums;

enum CommitteeStatus: string
{
    case ACTIVE = 'active';
    case ARCHIVED = 'archived';
}