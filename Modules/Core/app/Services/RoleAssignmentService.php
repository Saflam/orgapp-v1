<?php

namespace Modules\Core\Services;

use App\Models\OrganizationMembership;
use Modules\Core\Contracts\AuthorizationContext;
use Modules\Core\Models\Role;
use Modules\Core\Models\RoleAssignment;

class RoleAssignmentService
{
    public function assign(
        OrganizationMembership $membership,
        Role $role,
        AuthorizationContext $context,
        ?\Carbon\Carbon $startsAt = null,
        ?\Carbon\Carbon $endsAt = null,
    ): RoleAssignment {
        if (
            $role->organization_id !== null
            && $membership->organization_id !== $role->organization_id
        ) {
            throw new \InvalidArgumentException(
                'The membership and role must belong to the same organization.'
            );
        }

        if ($membership->organization_id !== $context->organizationId()) {
            throw new \InvalidArgumentException(
                'The authorization context must belong to the same organization.'
            );
        }

        if (
            $startsAt !== null
            && $endsAt !== null
            && $endsAt->lt($startsAt)
        ) {
            throw new \InvalidArgumentException(
                'The assignment end date must be after the start date.'
            );
        }

        return RoleAssignment::firstOrCreate(
            [
                'organization_membership_id' => $membership->id,
                'role_id' => $role->id,
                'context_type' => $context->contextType(),
                'context_id' => $context->contextId(),
            ],
            [
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]
        );
    }

    public function revoke(RoleAssignment $assignment): void
    {
        $assignment->delete();
    }
}