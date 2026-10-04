<?php

namespace Modules\Member\Services;

use Illuminate\Support\Facades\DB;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberDependant;

class MemberDependantService
{
    public function create(
        int $organizationId,
        array $data,
    ): MemberDependant {
        return DB::transaction(function () use (
            $organizationId,
            $data,
        ) {
            return MemberDependant::create([
                'organization_id' => $organizationId,
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);
        });
    }

    public function update(
        MemberDependant $dependant,
        array $data,
    ): MemberDependant {
        if ($dependant->converted_member_id !== null) {
            throw new \DomainException(
                'A converted dependant cannot be updated.'
            );
        }

        $dependant->update([
            'first_name' => $data['first_name'] ?? $dependant->first_name,
            'middle_name' => $data['middle_name'] ?? $dependant->middle_name,
            'last_name' => $data['last_name'] ?? $dependant->last_name,
            'date_of_birth' => $data['date_of_birth'] ?? $dependant->date_of_birth,
            'gender' => $data['gender'] ?? $dependant->gender,
            'metadata' => $data['metadata'] ?? $dependant->metadata,
        ]);

        return $dependant->refresh();
    }

    public function markAsConverted(
        MemberDependant $dependant,
        Member $member,
    ): MemberDependant {
        if ($dependant->converted_member_id !== null) {
            throw new \DomainException(
                'This dependant has already been converted to a member.'
            );
        }

        if ($dependant->organization_id !== $member->organization_id) {
            throw new \DomainException(
                'The dependant and member must belong to the same organization.'
            );
        }

        $dependant->update([
            'converted_member_id' => $member->id,
            'converted_at' => now()->toDateString(),
        ]);

        return $dependant->refresh();
    }
}