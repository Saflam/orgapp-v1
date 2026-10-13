<?php

namespace Modules\Events\Enums;

enum EventVisibility: string
{
    case Public = 'public';
    case Restricted = 'restricted';
}
