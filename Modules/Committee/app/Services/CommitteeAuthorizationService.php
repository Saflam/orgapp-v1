<?php

namespace Modules\Committee\Services;

use Modules\Committee\Enums\CommitteeMembershipStatus;
use Modules\Committee\Enums\CommitteeStatus;
use Modules\Committee\Enums\CommitteeTermStatus;
use Modules\Committee\Models\CommitteeMembership;
use Modules\Member\Models\Member;

class CommitteeAuthorizationService
{
    /**
     * Determine whether a member currently has a permission
     * through any effective committee membership.
     */
    public function hasPermission(
        Member $member,
        string $permissionCode,
    ): bool {
        return CommitteeMembership::query()
            ->where('member_id', $member->id)
            ->where('status', CommitteeMembershipStatus::ACTIVE)
            ->whereHas('committeeTerm', function ($query) {
                $query
                    ->where('status', CommitteeTermStatus::ACTIVE)
                    ->whereHas('committee', function ($query) {
                        $query
                            ->where(
                                'status',
                                CommitteeStatus::ACTIVE
                            )
                            ->whereHas('organization', function ($query) {
                                $query->whereHas('modules', function ($query) {
                                    $query
                                        ->where(
                                            'module',
                                            'Committee'
                                        )
                                        ->where('is_enabled', true);
                                });
                            });
                    });
            })
            ->whereHas('designation', function ($query) use ($permissionCode) {
                $query
                    ->where('is_active', true)
                    ->whereHas('permissions', function ($query) use ($permissionCode) {
                        $query->where('code', $permissionCode);
                    });
            })
            ->exists();
    }

    /**
     * Determine whether a member has at least one
     * permission from the supplied permission codes.
     */
    public function hasAnyPermission(
        Member $member,
        array $permissionCodes,
    ): bool {
        if ($permissionCodes === []) {
            return false;
        }

        return CommitteeMembership::query()
            ->where('member_id', $member->id)
            ->where('status', CommitteeMembershipStatus::ACTIVE)
            ->whereHas('committeeTerm', function ($query) {
                $query
                    ->where('status', CommitteeTermStatus::ACTIVE)
                    ->whereHas('committee', function ($query) {
                        $query
                            ->where(
                                'status',
                                CommitteeStatus::ACTIVE
                            )
                            ->whereHas('organization', function ($query) {
                                $query->whereHas('modules', function ($query) {
                                    $query
                                        ->where(
                                            'module',
                                            'Committee'
                                        )
                                        ->where('is_enabled', true);
                                });
                            });
                    });
            })
            ->whereHas('designation', function ($query) use ($permissionCodes) {
                $query
                    ->where('is_active', true)
                    ->whereHas('permissions', function ($query) use ($permissionCodes) {
                        $query->whereIn('code', $permissionCodes);
                    });
            })
            ->exists();
    }

    /**
     * Determine whether a member has every permission
     * from the supplied permission codes.
     */
    public function hasAllPermissions(
        Member $member,
        array $permissionCodes,
    ): bool {
        if ($permissionCodes === []) {
            return true;
        }

        foreach ($permissionCodes as $permissionCode) {
            if (! $this->hasPermission($member, $permissionCode)) {
                return false;
            }
        }

        return true;
    }
}