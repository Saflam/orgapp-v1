<?php

namespace Modules\Member\Services;

use Illuminate\Support\Facades\DB;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberDependant;
use Modules\Member\Models\MemberDependantRelationship;

class MemberDependantRelationshipService
{
    public function add(
        Member $member,
        MemberDependant $dependant,
        string $relationshipType,
        ?string $startedAt = null,
    ): MemberDependantRelationship {
        $this->ensureSameOrganization(
            $member,
            $dependant,
        );

        $this->ensureDependantIsNotConverted($dependant);

        $this->ensureNoDuplicateActiveRelationship(
            member: $member,
            dependant: $dependant,
            relationshipType: $relationshipType,
        );

        $this->ensureRelationshipAllowed(
            member: $member,
            dependant: $dependant,
            relationshipType: $relationshipType,
        );

        return DB::transaction(function () use (
            $member,
            $dependant,
            $relationshipType,
            $startedAt,
        ) {
            return MemberDependantRelationship::create([
                'member_id' => $member->id,
                'dependant_id' => $dependant->id,
                'relationship_type' => $relationshipType,
                'started_at' => $startedAt ?? now()->toDateString(),
                'ended_at' => null,
            ]);
        });
    }

    public function update(
        MemberDependantRelationship $relationship,
        ?string $relationshipType = null,
        ?string $startedAt = null,
        ?string $endedAt = null,
    ): MemberDependantRelationship {
        $relationship->loadMissing([
            'member',
            'dependant',
        ]);

        $this->ensureSameOrganization(
            $relationship->member,
            $relationship->dependant,
        );

        if ($relationshipType !== null) {
            $this->ensureRelationshipAllowed(
                member: $relationship->member,
                dependant: $relationship->dependant,
                relationshipType: $relationshipType,
                currentRelationship: $relationship,
            );
        }

        $effectiveStartDate = $startedAt ?? $relationship->started_at?->toDateString();
        $effectiveEndDate = $endedAt ?? $relationship->ended_at?->toDateString();

        if (
            $effectiveStartDate !== null
            && $effectiveEndDate !== null
            && $effectiveEndDate < $effectiveStartDate
        ) {
            throw new \DomainException(
                'The relationship end date cannot be before the start date.'
            );
        }

        $relationship->update([
            'relationship_type' => $relationshipType ?? $relationship->relationship_type,
            'started_at' => $startedAt ?? $relationship->started_at,
            'ended_at' => $endedAt ?? $relationship->ended_at,
        ]);

        return $relationship->refresh();
    }

    public function end(
        MemberDependantRelationship $relationship,
        ?string $endedAt = null,
    ): MemberDependantRelationship {
        if ($relationship->ended_at !== null) {
            throw new \DomainException(
                'Only an active dependant relationship can be ended.'
            );
        }

        $endDate = $endedAt ?? now()->toDateString();

        if (
            $relationship->started_at !== null
            && $endDate < $relationship->started_at->toDateString()
        ) {
            throw new \DomainException(
                'The relationship end date cannot be before the start date.'
            );
        }

        $relationship->update([
            'ended_at' => $endDate,
        ]);

        return $relationship->refresh();
    }

    private function ensureSameOrganization(
        Member $member,
        MemberDependant $dependant,
    ): void {
        if ($member->organization_id !== $dependant->organization_id) {
            throw new \DomainException(
                'The member and dependant must belong to the same organization.'
            );
        }
    }

    private function ensureDependantIsNotConverted(
        MemberDependant $dependant,
    ): void {
        if ($dependant->converted_member_id !== null) {
            throw new \DomainException(
                'A converted dependant cannot be assigned as a dependant relationship.'
            );
        }
    }

    private function ensureNoDuplicateActiveRelationship(
        Member $member,
        MemberDependant $dependant,
        string $relationshipType,
    ): void {
        $exists = MemberDependantRelationship::query()
            ->where('member_id', $member->id)
            ->where('dependant_id', $dependant->id)
            ->where('relationship_type', $relationshipType)
            ->whereNull('ended_at')
            ->exists();

        if ($exists) {
            throw new \DomainException(
                'This active dependant relationship already exists.'
            );
        }
    }

    private function ensureRelationshipAllowed(
        Member $member,
        MemberDependant $dependant,
        string $relationshipType,
        ?MemberDependantRelationship $currentRelationship = null,
    ): void {
        if ($relationshipType === 'spouse') {
            $exists = MemberDependantRelationship::query()
                ->where('member_id', $member->id)
                ->where('relationship_type', 'spouse')
                ->whereNull('ended_at')
                ->when(
                    $currentRelationship,
                    fn ($query) => $query->where(
                        'id',
                        '!=',
                        $currentRelationship->id
                    )
                )
                ->exists();

            if ($exists) {
                throw new \DomainException(
                    'A member can have only one active spouse relationship.'
                );
            }
        }
    }
}