<?php

namespace Modules\Member\Services;

use App\Models\OrganizationMembership;
use App\Models\UserAddress;
use App\Models\User;
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