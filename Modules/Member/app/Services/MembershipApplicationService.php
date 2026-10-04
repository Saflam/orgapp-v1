<?php

namespace Modules\Member\Services;

use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Models\MembershipApplication;
use Modules\Member\Models\MembershipType;

class MembershipApplicationService
{
    public function startOrResume(
        Organization $organization,
        int $userId,
        int $membershipTypeId,
    ): MembershipApplication {
        $membershipType = MembershipType::query()
            ->whereKey($membershipTypeId)
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->first();

        if (! $membershipType) {
            throw ValidationException::withMessages([
                'membership_type_id' =>
                    'The selected membership type is not available for this organization.',
            ]);
        }

        $application = MembershipApplication::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $userId)
            ->where('status', MembershipApplicationStatus::DRAFT)
            ->latest('id')
            ->first();

        if ($application) {
            return $application;
        }

        return MembershipApplication::create([
            'organization_id' => $organization->id,
            'user_id' => $userId,
            'membership_type_id' => $membershipType->id,
            'status' => MembershipApplicationStatus::DRAFT,
            'current_step' => 1,
            'completed_steps' => [],
            'data' => [],
        ]);
    }
}