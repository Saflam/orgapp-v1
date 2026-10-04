<?php

namespace Modules\Core\Services;

use App\Models\OrganizationMembership;
use App\Models\User;
use Modules\Core\Models\Organization;

class OrganizationAccessService
{
    public function canAccess(User $user, Organization $organization): bool
    {
        return OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    public function ensureAccess(User $user, Organization $organization): void
    {
        if (! $this->canAccess($user, $organization)) {
            abort(403, 'You do not have access to this organization.');
        }
    }
}