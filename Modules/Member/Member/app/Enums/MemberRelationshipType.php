<?php

namespace Modules\Member\Enums;

enum MemberRelationshipType: string
{
    case PARENT = 'parent';
    case CHILD = 'child';
    case SPOUSE = 'spouse';
    case SIBLING = 'sibling';
    case GUARDIAN = 'guardian';
    case DEPENDENT = 'dependent';
}