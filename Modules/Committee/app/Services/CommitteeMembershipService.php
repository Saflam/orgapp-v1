<?php

namespace Modules\Committee\Services;

use Illuminate\Support\Facades\DB;
use Modules\Committee\Enums\CommitteeMembershipStatus;
use Modules\Committee\Enums\CommitteeStatus;
use Modules\Committee\Enums\CommitteeTermStatus;
use Modules\Committee\Models\CommitteeMembership;
use Modules\Committee\Models\CommitteeTerm;
use Modules\Committee\Models\Designation;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Member\Models\Member;
use Carbon\Carbon;

class CommitteeMembershipService
{
    public function __construct(
        private readonly OrganizationModuleService $moduleService,
    ) {}
    
    public function addMember(
        CommitteeTerm $term,
        Member $member,
        Designation $designation,
    ): CommitteeMembership {

        $this->ensureCommitteeModuleEnabled(
            $term->committee->organization_id
        );

        $this->ensureValidAssignment(
            term: $term,
            member: $member,
            designation: $designation,
        );

        return DB::transaction(function () use (
            $term,
            $member,
            $designation,
        ) {
            return CommitteeMembership::create([
                'committee_term_id' => $term->id,
                'member_id' => $member->id,
                'designation_id' => $designation->id,
                'start_date' => $term->start_date,
                'end_date' => null,
                'status' => CommitteeMembershipStatus::ACTIVE,
            ]);
        });
    }

    public function update(
        CommitteeMembership $membership,
        int $designationId,
    ): CommitteeMembership {
        $this->ensureCommitteeModuleEnabled(
            $membership->committeeTerm->committee->organization_id
        );

        $membership->loadMissing([
            'committeeTerm.committee',
        ]);

        if ($membership->status !== CommitteeMembershipStatus::ACTIVE) {
            throw new \DomainException(
                'Only an active committee membership can be updated.'
            );
        }

        $designation = Designation::query()
            ->findOrFail($designationId);

        $organizationId = $membership->committeeTerm->committee->organization_id;

        if ($designation->organization_id !== $organizationId) {
            throw new \DomainException(
                'The designation does not belong to the committee organization.'
            );
        }

        if (! $designation->is_active) {
            throw new \DomainException(
                'An inactive designation cannot be assigned.'
            );
        }

        $this->ensureDesignationScopeMatchesCommittee(
            $designation,
            $membership->committeeTerm->committee,
        );

        if ($membership->designation_id === $designation->id) {
            return $membership->refresh();
        }

        $alreadyAssigned = CommitteeMembership::query()
            ->where('committee_term_id', $membership->committee_term_id)
            ->where('member_id', $membership->member_id)
            ->where('status', CommitteeMembershipStatus::ACTIVE)
            ->where('id', '!=', $membership->id)
            ->exists();

        if ($alreadyAssigned) {
            throw new \DomainException(
                'This member already has another active designation in this committee term.'
            );
        }

        $membership->update([
            'designation_id' => $designation->id,
        ]);

        return $membership->refresh();
    }

    public function changeDesignation(
        CommitteeMembership $membership,
        Designation $newDesignation,
        Carbon $effectiveDate,
    ): CommitteeMembership {
        $membership->loadMissing([
            'committeeTerm.committee',
        ]);

        $organizationId = $membership->committeeTerm->committee->organization_id;

        $this->ensureCommitteeModuleEnabled($organizationId);

        if ($membership->status !== CommitteeMembershipStatus::ACTIVE) {
            throw new \DomainException(
                'Only an active committee membership can have its designation changed.'
            );
        }

        $term = $membership->committeeTerm;

        if ($term->status !== CommitteeTermStatus::ACTIVE) {
            throw new \DomainException(
                'A designation can only be changed within an active committee term.'
            );
        }

        if (! $newDesignation->is_active) {
            throw new \DomainException(
                'An inactive designation cannot be assigned.'
            );
        }

        if ($newDesignation->organization_id !== $organizationId) {
            throw new \DomainException(
                'The designation does not belong to the committee organization.'
            );
        }

        $this->ensureDesignationScopeMatchesCommittee(
            $newDesignation,
            $term->committee,
        );

        $membershipStartDate = $membership->start_date;

        if (
            $membershipStartDate !== null
            && $effectiveDate->lte($membershipStartDate)
        ) {
            throw new \DomainException(
                'The designation change date must be after the membership start date.'
            );
        }

        if (
            $term->end_date !== null
            && $effectiveDate->gt($term->end_date)
        ) {
            throw new \DomainException(
                'The designation change date cannot be after the committee term ends.'
            );
        }

        return DB::transaction(function () use (
            $membership,
            $newDesignation,
            $effectiveDate,
        ) {
            $membership->update([
                'end_date' => $effectiveDate->copy()->subDay(),
                'status' => CommitteeMembershipStatus::ENDED,
            ]);

            return CommitteeMembership::create([
                'committee_term_id' => $membership->committee_term_id,
                'member_id' => $membership->member_id,
                'designation_id' => $newDesignation->id,
                'start_date' => $effectiveDate,
                'end_date' => null,
                'status' => CommitteeMembershipStatus::ACTIVE,
            ]);
        });
    }

    private function ensureCommitteeModuleEnabled(
        int $organizationId,
    ): void {
        $organization = Organization::query()
            ->findOrFail($organizationId);

        if (! $this->moduleService->isEnabled(
            $organization,
            'Committee',
        )) {
            throw new \DomainException(
                'The Committee module is not enabled for this organization.'
            );
        }
    }

    private function ensureValidAssignment(
        CommitteeTerm $term,
        Member $member,
        Designation $designation,
    ): void {
        if ($term->status !== CommitteeTermStatus::ACTIVE) {
            throw new \DomainException(
                'Members can only be added to an active committee term.'
            );
        }

        if (! $designation->is_active) {
            throw new \DomainException(
                'An inactive designation cannot be assigned.'
            );
        }

        $organizationId = $term->committee->organization_id;

        if ($designation->organization_id !== $organizationId) {
            throw new \DomainException(
                'The designation does not belong to the committee organization.'
            );
        }

        if ($member->organization_id !== $organizationId) {
            throw new \DomainException(
                'The member does not belong to the committee organization.'
            );
        }

        $this->ensureDesignationScopeMatchesCommittee(
            $designation,
            $term->committee,
        );

        $alreadyAssigned = CommitteeMembership::query()
            ->where('committee_term_id', $term->id)
            ->where('member_id', $member->id)
            ->where('status', CommitteeMembershipStatus::ACTIVE)
            ->exists();

        if ($alreadyAssigned) {
            throw new \DomainException(
                'This member already has a designation in this committee term.'
            );
        }
    }

    private function ensureDesignationScopeMatchesCommittee(
        Designation $designation,
        \Modules\Committee\Models\Committee $committee,
    ): void {
        if (
            $designation->scope === \Modules\Committee\Enums\DesignationScope::CENTRAL
            && $committee->unit_id !== null
        ) {
            throw new \DomainException(
                'A central designation cannot be assigned to a unit committee.'
            );
        }

        if (
            $designation->scope === \Modules\Committee\Enums\DesignationScope::UNIT
            && $committee->unit_id === null
        ) {
            throw new \DomainException(
                'A unit designation cannot be assigned to a central committee.'
            );
        }
    }

    public function end(
        CommitteeMembership $membership,
    ): CommitteeMembership {
        if ($membership->status !== CommitteeMembershipStatus::ACTIVE) {
            throw new \DomainException(
                'Only an active committee membership can be ended.'
            );
        }

        $membership->update([
            'status' => CommitteeMembershipStatus::ENDED,
        ]);

        return $membership->refresh();
    }

    public function cancel(
        CommitteeMembership $membership,
    ): CommitteeMembership {
        if ($membership->status !== CommitteeMembershipStatus::ACTIVE) {
            throw new \DomainException(
                'Only an active committee membership can be cancelled.'
            );
        }

        $membership->update([
            'status' => CommitteeMembershipStatus::CANCELLED,
        ]);

        return $membership->refresh();
    }

    public function isEffective(
        CommitteeMembership $membership
    ): bool {
        $membership->loadMissing([
            'committeeTerm.committee',
        ]);

        return
            $membership->status === CommitteeMembershipStatus::ACTIVE
            && $membership->committeeTerm->status === CommitteeTermStatus::ACTIVE
            && $membership->committeeTerm->committee->status === CommitteeStatus::ACTIVE;
    }

    public function getEffectiveMemberships(
        CommitteeTerm $term,
        ?Carbon $date = null,
    ) {
        $this->ensureCommitteeModuleEnabled(
            $term->committee->organization_id
        );

        if ($term->status !== CommitteeTermStatus::ACTIVE) {
            throw new \DomainException(
                'Effective memberships can only be retrieved from an active committee term.'
            );
        }

        $date ??= Carbon::today();

        return CommitteeMembership::query()
            ->where('committee_term_id', $term->id)
            ->whereDate('start_date', '<=', $date)
            ->where(function ($query) use ($date) {
                $query
                    ->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $date);
            })
            ->with([
                'member',
                'designation',
            ])
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();
    }
}