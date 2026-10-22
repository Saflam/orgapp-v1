<?php

namespace Modules\Core\Services;

use App\Models\User;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Permission;
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
        // System administrators manage the platform and initial organization setup;
        // they do not inherit permissions for day-to-day organization operations.
        if ($user->is_system_admin) {
            return false;
        }

        $membership = $user->organizationMemberships()
            ->where('organization_id', $context->organizationId)
            ->where('status', 'active')
            ->first();

        if ($membership === null) {
            return false;
        }

        // The organization Super Administrator has broad access to known permissions
        // outside Finance. The assignment must be active and organization-scoped.
        $isOrganizationSuperAdmin = RoleAssignment::query()
            ->active()
            ->where('organization_membership_id', $membership->id)
            ->where('context_type', 'organization')
            ->where('context_id', $context->organizationId)
            ->whereHas('role', fn ($query) => $query
                ->whereNull('organization_id')
                ->where('code', 'super_admin')
                ->where('is_system', true))
            ->exists();

        if ($isOrganizationSuperAdmin) {
            return Permission::query()
                ->where('code', $permissionCode)
                ->exists();
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
            return (string) $assignment->context_id === (string) $context->organizationId;
        }

        if ($assignment->context_type === 'unit') {
            return $context->unitId !== null
                && (string) $assignment->context_id === (string) $context->unitId;
        }

        return false;
    }
}
