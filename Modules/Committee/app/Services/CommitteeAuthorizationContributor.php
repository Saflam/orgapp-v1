<?php

namespace Modules\Committee\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Committee\Enums\CommitteeMembershipStatus;
use Modules\Committee\Enums\CommitteeStatus;
use Modules\Committee\Enums\CommitteeTermStatus;
use Modules\Committee\Enums\DesignationScope;
use Modules\Committee\Models\CommitteeMembership;
use Modules\Core\Contracts\AuthorizationContributor;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\OrganizationModule;
use Modules\Core\Models\Permission;
use Modules\Member\Models\Member;

class CommitteeAuthorizationContributor implements AuthorizationContributor
{
    public function can(
        User $user,
        string $permissionCode,
        AuthorizationContext $context,
    ): bool {
        $permission = Permission::query()
            ->where('code', $permissionCode)
            ->first();

        if (! $permission || ! $this->permissionModuleIsEnabled($context->organizationId, $permission->module)) {
            return false;
        }

        $member = Member::query()
            ->where('organization_id', $context->organizationId)
            ->where('user_id', $user->id)
            ->first();

        if (! $member) {
            return false;
        }

        $today = Carbon::today();

        $memberships = CommitteeMembership::query()
            ->where('member_id', $member->id)
            ->where('status', CommitteeMembershipStatus::ACTIVE)
            ->where(function ($query) use ($today): void {
                $query->whereNull('start_date')->orWhereDate('start_date', '<=', $today);
            })
            ->where(function ($query) use ($today): void {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            })
            ->whereHas('committeeTerm', function ($query) use ($context, $today): void {
                $query
                    ->where('status', CommitteeTermStatus::ACTIVE)
                    ->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today)
                    ->whereHas('committee', function ($query) use ($context): void {
                        $query
                            ->where('organization_id', $context->organizationId)
                            ->where('status', CommitteeStatus::ACTIVE)
                            ->whereHas('organization.modules', function ($query): void {
                                $query
                                    ->where('module', 'Committee')
                                    ->where('is_enabled', true);
                            });
                    });
            })
            ->whereHas('designation', function ($query) use ($context): void {
                $query->where('organization_id', $context->organizationId);
            })
            ->with([
                'designation.permissions',
                'committeeTerm.committee',
            ])
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

    private function permissionModuleIsEnabled(int $organizationId, string $module): bool
    {
        $normalizedModule = strtolower(trim($module));

        // Core and Member are required modules for every organization.
        if (in_array($normalizedModule, ['core', 'member'], true)) {
            return true;
        }

        return OrganizationModule::query()
            ->where('organization_id', $organizationId)
            ->whereRaw('LOWER(module) = ?', [$normalizedModule])
            ->where('is_enabled', true)
            ->exists();
    }
}
