<?php

namespace Modules\Core\Services;

use App\Models\User;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\RoleAssignment;

class AuthorizationService
{
    public function __construct(
        private AuthorizationContributorRegistry $contributorRegistry,
    ) {
    }

    public function can(
        User $user,
        string $permissionCode,
        AuthorizationContext $context,
    ): bool {
        $membership = $user->organizationMemberships()
            ->where('organization_id', $context->organizationId)
            ->where('status', 'active')
            ->first();

        if ($membership === null) {
            return false;
        }

        $assignments = RoleAssignment::query()
            ->active()
            ->where('organization_membership_id', $membership->id)
            ->whereHas('role', function ($query) use ($context) {
                $query->where(function ($query) use ($context) {
                    $query
                        ->whereNull('organization_id')
                        ->orWhere(
                            'organization_id',
                            $context->organizationId
                        );
                });
            })
            ->whereHas(
                'role.permissions',
                fn ($query) => $query->where('code', $permissionCode)
            )
            ->get();

        foreach ($assignments as $assignment) {
            if ($this->contextMatches($assignment, $context)) {
                return true;
            }
        }

        foreach ($this->contributorRegistry->all() as $contributor) {
            if ($contributor->can($user, $permissionCode, $context)) {
                return true;
            }
        }

        return false;
    }

    private function contextMatches(
        RoleAssignment $assignment,
        AuthorizationContext $context,
    ): bool {
        if ($assignment->context_type === 'organization') {
            return $assignment->context_id === $context->organizationId;
        }

        if ($assignment->context_type === 'unit') {
            return $context->unitId !== null
                && $assignment->context_id === $context->unitId;
        }

        return false;
    }
}
