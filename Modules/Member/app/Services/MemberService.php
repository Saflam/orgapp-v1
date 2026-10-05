<?php

namespace Modules\Member\Services;

use App\Models\OrganizationMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Unit;
use Modules\Member\Models\Member;

class MemberService
{
    public function create(array $data): Member
    {
        return DB::transaction(function () use ($data) {
            $organizationId = $data['organization_id'];
            $userId = $data['user_id'];

            $this->ensureUserBelongsToOrganization(
                $organizationId,
                $userId
            );

            $this->ensureUnitBelongsToOrganization(
                $organizationId,
                $data['unit_id'] ?? null
            );

            $this->ensureMembershipNumberIsAvailable(
                $organizationId,
                $data['membership_number']
            );

            $this->ensureUserIsNotAlreadyMember(
                $organizationId,
                $userId
            );

            $member = Member::create(
                array_intersect_key(
                    $data,
                    array_flip([
                        'organization_id',
                        'user_id',
                        'unit_id',
                        'membership_number',
                        'joined_at',
                        'metadata',
                    ])
                )
            );

            $userDetailData = array_intersect_key(
                $data,
                array_flip([
                    'first_name',
                    'middle_name',
                    'last_name',
                    'date_of_birth',
                    'gender',
                    'blood_group',
                    'whatsapp',
                    'whatsapp_calling_code',
                    'home_contact',
                    'profession',
                    'company',
                ])
            );

            if ($userDetailData !== []) {
                $member->user->details()->updateOrCreate(
                    [],
                    $userDetailData,
                );
            }

            return $member->load([
                'user.details',
                'unit',
            ]);
        });
    }

    public function update(
        Member $member,
        array $data,
    ): Member {
        return DB::transaction(function () use ($member, $data) {
            $organizationId = $member->organization_id;

            if (array_key_exists('unit_id', $data)) {
                $this->ensureUnitBelongsToOrganization(
                    $organizationId,
                    $data['unit_id']
                );
            }

            if (
                array_key_exists('membership_number', $data)
                && $data['membership_number'] !== $member->membership_number
            ) {
                $this->ensureMembershipNumberIsAvailable(
                    $organizationId,
                    $data['membership_number'],
                    $member->id
                );
            }

            $memberData = array_intersect_key(
                $data,
                array_flip([
                    'unit_id',
                    'membership_number',
                    'joined_at',
                    'metadata',
                ])
            );

            if ($memberData !== []) {
                $member->update($memberData);
            }

            $userDetailData = array_intersect_key(
                $data,
                array_flip([
                    'first_name',
                    'middle_name',
                    'last_name',
                    'date_of_birth',
                    'gender',
                    'blood_group',
                    'whatsapp',
                    'whatsapp_calling_code',
                    'home_contact',
                    'profession',
                    'company',
                ])
            );

            if ($userDetailData !== []) {
                $member->user->details()->updateOrCreate(
                    [],
                    $userDetailData,
                );
            }

            return $member->refresh()->load([
                'user.details',
                'unit',
            ]);
        });
    }

    private function ensureUserBelongsToOrganization(
        int $organizationId,
        int $userId,
    ): void {
        $exists = OrganizationMembership::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'user_id' => 'The selected user is not a member of the selected organization.',
            ]);
        }
    }

    private function ensureUnitBelongsToOrganization(
        int $organizationId,
        ?int $unitId,
    ): void {
        if ($unitId === null) {
            return;
        }

        $exists = Unit::query()
            ->where('id', $unitId)
            ->where('organization_id', $organizationId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'unit_id' => 'The selected unit does not belong to the selected organization.',
            ]);
        }
    }

    private function ensureMembershipNumberIsAvailable(
        int $organizationId,
        string $membershipNumber,
        ?int $ignoreMemberId = null,
    ): void {
        $query = Member::query()
            ->where('organization_id', $organizationId)
            ->where('membership_number', $membershipNumber);

        if ($ignoreMemberId !== null) {
            $query->whereKeyNot($ignoreMemberId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'membership_number' => 'The membership number has already been used in this organization.',
            ]);
        }
    }

    private function ensureUserIsNotAlreadyMember(
        int $organizationId,
        int $userId,
    ): void {
        $exists = Member::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'user_id' => 'The selected user is already a member of this organization.',
            ]);
        }
    }
}