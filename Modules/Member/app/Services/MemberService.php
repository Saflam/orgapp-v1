<?php

namespace Modules\Member\Services;

use App\Models\OrganizationMembership;
use App\Models\UserAddress;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Unit;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberRelationship;

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
                $data['membership_number'],
                sharedMemberId: isset($data['shared_membership_with_member_id'])
                    ? (int) $data['shared_membership_with_member_id']
                    : null,
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

            app(MemberUnitHistoryService::class)->ensureInitial($member);

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
                    'company_address',
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
        ?User $actor = null,
    ): Member {
        return DB::transaction(function () use ($member, $data, $actor) {
            $before = $this->snapshot($member);
            $organizationId = $member->organization_id;

            if (array_key_exists('unit_id', $data)) {
                $this->ensureUnitBelongsToOrganization(
                    $organizationId,
                    $data['unit_id']
                );
            }

            $spouseMemberIds = [];

            if (
                array_key_exists('membership_number', $data)
                && $data['membership_number'] !== $member->membership_number
            ) {
                $spouseMemberIds = $this->activeSpouseMemberIds($member);

                $this->ensureMembershipNumberIsAvailable(
                    $organizationId,
                    $data['membership_number'],
                    ignoreMemberId: $member->id,
                    allowedMemberIds: $spouseMemberIds,
                );
            }

            $userData = array_intersect_key(
                $data,
                array_flip([
                    'name',
                    'email',
                    'phone',
                ])
            );

            if ($userData !== []) {
                $member->user->update($userData);
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

            $unitChanged = array_key_exists('unit_id', $memberData)
                && $memberData['unit_id'] !== $member->unit_id;

            if ($unitChanged) {
                $member = Member::query()
                    ->whereKey($member->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($memberData['unit_id'] !== $member->unit_id) {
                    app(MemberUnitHistoryService::class)->changeUnit(
                        member: $member,
                        newUnitId: $memberData['unit_id'],
                        actorUserId: $actor?->id,
                        source: 'manual',
                        reason: $data['reason'] ?? null,
                    );
                }
            }

            if ($memberData !== []) {
                $member->update($memberData);

                // A membership number identifies the family membership. Keep
                // the active spouse record(s) on the same number when it changes.
                if (array_key_exists('membership_number', $memberData) && $spouseMemberIds !== []) {
                    Member::query()
                        ->where('organization_id', $organizationId)
                        ->whereIn('id', $spouseMemberIds)
                        ->update(['membership_number' => $memberData['membership_number']]);
                }
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
                    'company_address',
                ])
            );

            if ($userDetailData !== []) {
                $member->user->details()->updateOrCreate(
                    [],
                    $userDetailData,
                );
            }

            if (isset($data['address']) && is_array($data['address'])) {
                $address = $data['address'];
                $addressType = $address['address_type'] ?? 'home';
                unset($address['address_type']);
                $address['user_id'] = $member->user_id;
                UserAddress::query()->updateOrCreate(
                    ['user_id' => $member->user_id, 'address_type' => $addressType],
                    $address,
                );
            }

            $member->refresh()->load(['user.details', 'unit']);

            if ($actor !== null) {
                $member->changes()->create([
                    'actor_user_id' => $actor->id,
                    'reason' => $data['reason'] ?? null,
                    'before_data' => $before,
                    'after_data' => $this->snapshot($member),
                ]);
            }

            return $member->refresh()->load([
                'user.details',
                'unit',
            ]);
        });
    }

    private function snapshot(Member $member): array
    {
        $member->loadMissing(['user.details', 'unit']);

        return [
            'member' => $member->only([
                'id', 'organization_id', 'user_id', 'unit_id',
                'membership_number', 'joined_at', 'metadata',
            ]),
            'user' => $member->user?->only(['id', 'name', 'email', 'phone']),
            'details' => $member->user?->details?->toArray(),
            'addresses' => class_exists(UserAddress::class)
                ? UserAddress::query()->where('user_id', $member->user_id)->get()->toArray()
                : [],
        ];
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

    /**
     * A membership number is unique per family, not per individual member.
     * A second record may reuse it only when it is being added to an already
     * identified spouse family. Unrelated families remain unique.
     *
     * @param array<int, int> $allowedMemberIds
     */
    private function ensureMembershipNumberIsAvailable(
        int $organizationId,
        string $membershipNumber,
        ?int $ignoreMemberId = null,
        ?int $sharedMemberId = null,
        array $allowedMemberIds = [],
    ): void {
        if ($sharedMemberId !== null) {
            $sharedMember = Member::query()
                ->where('organization_id', $organizationId)
                ->whereKey($sharedMemberId)
                ->where('membership_number', $membershipNumber)
                ->first();

            if ($sharedMember === null) {
                throw ValidationException::withMessages([
                    'membership_number' => 'The selected spouse does not have this membership number in the organization.',
                ]);
            }

            $allowedMemberIds = array_merge(
                $allowedMemberIds,
                [$sharedMemberId],
                $this->activeSpouseMemberIds($sharedMember),
            );
        }

        $allowedMemberIds = array_values(array_unique(array_filter(
            array_map('intval', $allowedMemberIds),
            static fn (int $id): bool => $id > 0,
        )));

        $query = Member::query()
            ->where('organization_id', $organizationId)
            ->where('membership_number', $membershipNumber);

        if ($ignoreMemberId !== null) {
            $query->whereKeyNot($ignoreMemberId);
        }

        if ($allowedMemberIds !== []) {
            $query->whereNotIn('id', $allowedMemberIds);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'membership_number' => 'This membership number is already assigned to another family.',
            ]);
        }
    }

    /**
     * Return active spouse member IDs connected to the supplied member.
     *
     * @return array<int, int>
     */
    private function activeSpouseMemberIds(Member|int $member): array
    {
        $memberId = $member instanceof Member ? (int) $member->id : $member;
        $organizationId = $member instanceof Member ? (int) $member->organization_id : null;

        $relationships = MemberRelationship::query()
            ->whereNull('ended_at')
            ->whereIn('relationship_type', ['spouse', 'spouse_of'])
            ->where(function ($query) use ($memberId): void {
                $query->where('member_id', $memberId)
                    ->orWhere('related_member_id', $memberId);
            })
            ->get(['member_id', 'related_member_id']);

        $ids = $relationships->map(static function (MemberRelationship $relationship) use ($memberId): int {
            return (int) ($relationship->member_id === $memberId
                ? $relationship->related_member_id
                : $relationship->member_id);
        })->unique()->values()->all();

        if ($organizationId !== null && $ids !== []) {
            return Member::query()
                ->where('organization_id', $organizationId)
                ->whereIn('id', $ids)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
        }

        return $ids;
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