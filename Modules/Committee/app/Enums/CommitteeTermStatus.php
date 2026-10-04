<?php

namespace Modules\Committee\Enums;

enum CommitteeTermStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}