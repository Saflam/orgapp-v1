<?php

namespace Modules\Committee\Services;

use App\Models\User;
use Modules\Committee\Enums\CommitteeMembershipStatus;
use Modules\Committee\Enums\CommitteeStatus;
use Modules\Committee\Enums\CommitteeTermStatus;
use Modules\Committee\Enums\DesignationScope;
use Modules\Committee\Models\CommitteeMembership;
use Modules\Core\Contracts\AuthorizationContributor;
use Modules\Core\Data\AuthorizationContext;
use Modules\Member\Models\Member;

class CommitteeAuthorizationContributor implements AuthorizationContributor
{
    public function can(
        User $user,
        string $permissionCode,
        AuthorizationContext $context,
    ): bool {
        $member = Member::query()
            ->where('organization_id', $context->organizationId)
            ->where('user_id', $user->id)
            ->first();

        if (! $member) {
            return false;
        }

        $memberships = CommitteeMembership::query()
            ->where('member_id', $member->id)
            ->where('status', CommitteeMembershipStatus::ACTIVE)
            ->with([
                'designation.permissions',
                'committeeTerm.committee',
            ])
            ->whereHas('committeeTerm', function ($query) {
                $query
                    ->where('status', CommitteeTermStatus::ACTIVE)
                    ->whereHas('committee', function ($query) {
                        $query
                            ->where('status', CommitteeStatus::ACTIVE)
                            ->whereHas('organization.modules', function ($query) {
                                $query
                                    ->where('module', 'Committee')
                                    ->where('is_enabled', true);
                            });
                    });
            })
            ->get();

        foreach ($memberships as $membership) {
            $committee = $membership->committeeTerm?->committee;
            $designation = $membership->designation;

            if (! $committee || ! $designation || ! $designation->is_active) {
                continue;
            }

            if (! $designation->permissions->contains('code', $permissionCode)) {
                continue;
            }

            if ($designation->scope === DesignationScope::CENTRAL) {
                if ($committee->unit_id === null) {
                    return true;
                }

                if ($context->unitId !== null && $committee->unit_id === $context->unitId) {
                    return true;
                }

                continue;
            }

            if (
                $designation->scope === DesignationScope::UNIT
                && $committee->unit_id !== null
                && $context->unitId !== null
                && $committee->unit_id === $context->unitId
            ) {
                return true;
            }
        }

        return false;
    }
}
