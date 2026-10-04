<?php

namespace Modules\Member\Services;

use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Member\Enums\MemberRelationshipType;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberRelationship;

class MemberRelationshipService
{
    public function add(
        Member $member,
        Member $relatedMember,
        MemberRelationshipType $relationshipType,
        ?Carbon $startedAt = null,
        ?array $metadata = null,
    ): MemberRelationship {
        $this->ensureSameOrganization(
            member: $member,
            relatedMember: $relatedMember,
        );

        if ($member->id === $relatedMember->id) {
            throw new DomainException(
                'A member cannot have a relationship with themselves.'
            );
        }

        $startedAt ??= now()->startOfDay();

        $this->ensureValidDates(
            startedAt: $startedAt,
            endedAt: null,
        );

        $alreadyExists = MemberRelationship::query()
            ->where('member_id', $member->id)
            ->where('related_member_id', $relatedMember->id)
            ->where('relationship_type', $relationshipType->value)
            ->whereNull('ended_at')
            ->exists();

        if ($alreadyExists) {
            throw new DomainException(
                'This active relationship already exists.'
            );
        }

        return DB::transaction(function () use (
            $member,
            $relatedMember,
            $relationshipType,
            $startedAt,
            $metadata,
        ) {
            return MemberRelationship::create([
                'member_id' => $member->id,
                'related_member_id' => $relatedMember->id,
                'relationship_type' => $relationshipType->value,
                'started_at' => $startedAt,
                'ended_at' => null,
                'metadata' => $metadata,
            ]);
        });
    }

    public function update(
        MemberRelationship $relationship,
        Member $relatedMember,
        MemberRelationshipType $relationshipType,
        ?Carbon $startedAt = null,
        ?Carbon $endedAt = null,
        ?array $metadata = null,
    ): MemberRelationship {
        $relationship->loadMissing('member');

        $this->ensureSameOrganization(
            member: $relationship->member,
            relatedMember: $relatedMember,
        );

        if (
            $relationship->member_id === $relatedMember->id
        ) {
            throw new DomainException(
                'A member cannot have a relationship with themselves.'
            );
        }

        $startedAt ??= $relationship->started_at;

        $this->ensureValidDates(
            startedAt: $startedAt,
            endedAt: $endedAt,
        );

        if (
            $relationship->ended_at !== null
            && $endedAt === null
        ) {
            throw new DomainException(
                'An ended relationship cannot be reopened through an update.'
            );
        }

        return DB::transaction(function () use (
            $relationship,
            $relatedMember,
            $relationshipType,
            $startedAt,
            $endedAt,
            $metadata,
        ) {
            $relationship->update([
                'related_member_id' => $relatedMember->id,
                'relationship_type' => $relationshipType->value,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'metadata' => $metadata,
            ]);

            return $relationship->refresh();
        });
    }

    public function end(
        MemberRelationship $relationship,
        ?Carbon $endedAt = null,
    ): MemberRelationship {
        if ($relationship->ended_at !== null) {
            throw new DomainException(
                'This relationship has already ended.'
            );
        }

        $endedAt ??= now()->startOfDay();

        $this->ensureValidDates(
            startedAt: $relationship->started_at,
            endedAt: $endedAt,
        );

        $relationship->update([
            'ended_at' => $endedAt,
        ]);

        return $relationship->refresh();
    }

    private function ensureSameOrganization(
        Member $member,
        Member $relatedMember,
    ): void {
        if (
            $member->organization_id !==
            $relatedMember->organization_id
        ) {
            throw new DomainException(
                'Both members must belong to the same organization.'
            );
        }
    }

    private function ensureValidDates(
        ?Carbon $startedAt,
        ?Carbon $endedAt,
    ): void {
        if (
            $startedAt !== null &&
            $endedAt !== null &&
            $endedAt->lt($startedAt)
        ) {
            throw new DomainException(
                'The relationship end date cannot be before the start date.'
            );
        }
    }
}