<?php

namespace Modules\Core\Services;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Role;
use Modules\Core\Models\RoleAssignment;

class SuperAdminService
{
    private const ROLE_CODE = 'super_admin';

    private const CONTEXT_TYPE = 'organization';

    public function replace(
        Organization $organization,
        User $newSuperAdmin,
    ): void {
        $this->ensureSystemAdminContext();

        DB::transaction(function () use (
            $organization,
            $newSuperAdmin,
        ) {
            $role = $this->getSuperAdminRole();

            $membership = OrganizationMembership::query()
                ->where('organization_id', $organization->id)
                ->where('user_id', $newSuperAdmin->id)
                ->where('status', 'active')
                ->first();

            if ($membership === null) {
                throw ValidationException::withMessages([
                    'user' => 'The selected user does not have an active organization membership.',
                ]);
            }

            // End the current Super Admin assignment.
            RoleAssignment::query()
                ->whereHas('organizationMembership', function ($query) use ($organization) {
                    $query->where('organization_id', $organization->id);
                })
                ->where('role_id', $role->id)
                ->where('context_type', self::CONTEXT_TYPE)
                ->where('context_id', $organization->id)
                ->whereNull('ends_at')
                ->update([
                    'ends_at' => now(),
                ]);

            // Assign Super Admin to the new organization member.
            RoleAssignment::create([
                'organization_membership_id' => $membership->id,
                'role_id' => $role->id,
                'context_type' => self::CONTEXT_TYPE,
                'context_id' => $organization->id,
                'starts_at' => now(),
            ]);
        });
    }

    public function remove(
        Organization $organization,
    ): void {
        $this->ensureSystemAdminContext();

        RoleAssignment::query()
            ->whereHas('organizationMembership', function ($query) use ($organization) {
                $query->where('organization_id', $organization->id);
            })
            ->where('role_id', $this->getSuperAdminRole()->id)
            ->where('context_type', self::CONTEXT_TYPE)
            ->where('context_id', $organization->id)
            ->whereNull('ends_at')
            ->update([
                'ends_at' => now(),
            ]);
    }

    private function getSuperAdminRole(): Role
    {
        return Role::query()
            ->whereNull('organization_id')
            ->where('code', self::ROLE_CODE)
            ->where('is_system', true)
            ->firstOrFail();
    }

    private function ensureSystemAdminContext(): void
    {
        $user = auth()->user();

        if ($user === null || ! $user->is_system_admin) {
            abort(
                403,
                'Only the system administrator can manage the organization super admin.'
            );
        }
    }
}