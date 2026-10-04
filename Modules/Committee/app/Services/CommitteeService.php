<?php

namespace Modules\Committee\Services;

use Illuminate\Support\Facades\DB;
use Modules\Committee\Enums\CommitteeKind;
use Modules\Committee\Enums\CommitteeStatus;
use Modules\Committee\Enums\CommitteeTermStatus;
use Modules\Committee\Models\Committee;
use Modules\Committee\Models\CommitteeTerm;
use Modules\Committee\Models\CommitteeType;
use Modules\Core\Services\OrganizationModuleService;

class CommitteeService
{
    public function __construct(
        private readonly OrganizationModuleService $moduleService,
    ) {
    }

    public function createRecurringCommittee(
        int $organizationId,
        int $committeeTypeId,
        string $name,
        ?int $unitId = null,
        ?int $parentCommitteeId = null,
    ): Committee {

        $this->ensureOrganizationConsistency(
            organizationId: $organizationId,
            committeeTypeId: $committeeTypeId,
            unitId: $unitId,
            parentCommitteeId: $parentCommitteeId,
        );

        $this->ensureCommitteeModuleEnabled($organizationId);

        return DB::transaction(function () use (
            $organizationId,
            $committeeTypeId,
            $name,
            $unitId,
            $parentCommitteeId,
        ) {
            return Committee::create([
                'organization_id' => $organizationId,
                'committee_type_id' => $committeeTypeId,
                'unit_id' => $unitId,
                'parent_committee_id' => $parentCommitteeId,
                'name' => $name,
                'kind' => CommitteeKind::RECURRING,
                'status' => CommitteeStatus::ACTIVE,
            ]);
        });
    }

    public function createTemporaryCommittee(
        int $organizationId,
        int $committeeTypeId,
        string $name,
        ?int $unitId = null,
        ?int $parentCommitteeId = null,
    ): Committee {

        $this->ensureOrganizationConsistency(
            organizationId: $organizationId,
            committeeTypeId: $committeeTypeId,
            unitId: $unitId,
            parentCommitteeId: $parentCommitteeId,
        );

        $this->ensureCommitteeModuleEnabled($organizationId);

        return DB::transaction(function () use (
            $organizationId,
            $committeeTypeId,
            $name,
            $unitId,
            $parentCommitteeId,
        ) {
            return Committee::create([
                'organization_id' => $organizationId,
                'committee_type_id' => $committeeTypeId,
                'unit_id' => $unitId,
                'parent_committee_id' => $parentCommitteeId,
                'name' => $name,
                'kind' => CommitteeKind::TEMPORARY,
                'status' => CommitteeStatus::ACTIVE,
            ]);
        });
    }

    public function createTerm(
        Committee $committee,
        string $startDate,
        string $endDate,
    ): CommitteeTerm {
        $this->ensureCommitteeModuleEnabled(
            $committee->organization_id
        );

        if ($committee->kind !== CommitteeKind::RECURRING) {
            throw new \DomainException(
                'Only recurring committees can have terms.'
            );
        }

        if ($startDate >= $endDate) {
            throw new \InvalidArgumentException(
                'The term start date must be before the end date.'
            );
        }

        $this->ensureNoOverlappingTerm(
            $committee,
            $startDate,
            $endDate,
        );

        return CommitteeTerm::create([
            'committee_id' => $committee->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => CommitteeTermStatus::DRAFT,
        ]);
    }

    public function updateCommittee(
        Committee $committee,
        string $name,
        ?int $unitId = null,
        ?int $parentCommitteeId = null,
    ): Committee {
        $this->ensureOrganizationConsistency(
            organizationId: $committee->organization_id,
            committeeTypeId: $committee->committee_type_id,
            unitId: $unitId,
            parentCommitteeId: $parentCommitteeId,
        );

        $committee->update([
            'name' => $name,
            'unit_id' => $unitId,
            'parent_committee_id' => $parentCommitteeId,
        ]);

        return $committee->refresh();
    }

    public function archiveCommittee(Committee $committee): Committee
    {
        if ($committee->status === CommitteeStatus::ARCHIVED) {
            throw new \DomainException(
                'The committee is already archived.'
            );
        }

        $committee->update([
            'status' => CommitteeStatus::ARCHIVED,
        ]);

        return $committee->refresh();
    }

    public function activateTerm(CommitteeTerm $term): CommitteeTerm
    {
        if ($term->status !== CommitteeTermStatus::DRAFT) {
            throw new \DomainException(
                'Only draft committee terms can be activated.'
            );
        }

        $term->update([
            'status' => CommitteeTermStatus::ACTIVE,
        ]);

        return $term->refresh();
    }

    public function completeTerm(CommitteeTerm $term): CommitteeTerm
    {
        if ($term->status !== CommitteeTermStatus::ACTIVE) {
            throw new \DomainException(
                'Only active committee terms can be completed.'
            );
        }

        $term->update([
            'status' => CommitteeTermStatus::COMPLETED,
        ]);

        return $term->refresh();
    }

    public function updateTerm(
        CommitteeTerm $term,
        string $startDate,
        string $endDate,
    ): CommitteeTerm {
        if ($term->status === CommitteeTermStatus::COMPLETED) {
            throw new \DomainException(
                'Completed committee terms cannot be edited.'
            );
        }

        if ($startDate >= $endDate) {
            throw new \InvalidArgumentException(
                'The term start date must be before the end date.'
            );
        }

        $committee = $term->committee;

        $this->ensureNoOverlappingTerm(
            committee: $committee,
            startDate: $startDate,
            endDate: $endDate,
            ignoreTermId: $term->id,
        );

        $term->update([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $term->refresh();
    }

    private function ensureCommitteeModuleEnabled(
        int $organizationId,
    ): void {
        $organization = \Modules\Core\Models\Organization::query()
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

    private function ensureNoOverlappingTerm(
        Committee $committee,
        string $startDate,
        string $endDate,
        ?int $ignoreTermId = null,
    ): void {
        $query = $committee->terms()
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate);

        if ($ignoreTermId !== null) {
            $query->whereKeyNot($ignoreTermId);
        }

        if ($query->exists()) {
            throw new \DomainException(
                'The committee term overlaps an existing term.'
            );
        }
    }

    private function ensureOrganizationConsistency(
        int $organizationId,
        int $committeeTypeId,
        ?int $unitId,
        ?int $parentCommitteeId,
    ): void {
        $committeeType = CommitteeType::query()
            ->whereKey($committeeTypeId)
            ->where('organization_id', $organizationId)
            ->first();

        if (! $committeeType) {
            throw new \DomainException(
                'The committee type does not belong to the organization.'
            );
        }

        if ($unitId !== null) {
            $unitBelongsToOrganization = \Modules\Core\Models\Unit::query()
                ->whereKey($unitId)
                ->where('organization_id', $organizationId)
                ->exists();

            if (! $unitBelongsToOrganization) {
                throw new \DomainException(
                    'The unit does not belong to the organization.'
                );
            }
        }

        if ($parentCommitteeId !== null) {
            $parentBelongsToOrganization = Committee::query()
                ->whereKey($parentCommitteeId)
                ->where('organization_id', $organizationId)
                ->exists();

            if (! $parentBelongsToOrganization) {
                throw new \DomainException(
                    'The parent committee does not belong to the organization.'
                );
            }
        }
    }
}