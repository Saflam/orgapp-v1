<?php

namespace Modules\Core\Services;

use App\Models\OrganizationMembership;
use App\Models\User;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Role;

class SuperAdminProvisioningService
{
    private const ROLE_CODE = 'super_admin';

    public function __construct(
        private readonly RoleAssignmentService $roleAssignmentService,
    ) {
    }

    public function provision(
        Organization $organization,
        array $userData,
    ): User {
        $user = User::create([
            'name' => $userData['name'],
            'email' => $userData['email'],
            'phone' => $userData['phone'] ?? null,
        ]);

        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $role = $this->getSuperAdminRole();

        $this->roleAssignmentService->assign(
            membership: $membership,
            role: $role,
            context: $organization,
            startsAt: now(),
        );

        return $user->fresh();
    }

    private function getSuperAdminRole(): Role
    {
        return Role::query()
            ->whereNull('organization_id')
            ->where('code', self::ROLE_CODE)
            ->where('is_system', true)
            ->firstOrFail();
    }
}