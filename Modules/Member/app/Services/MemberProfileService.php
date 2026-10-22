<?php

namespace Modules\Member\Services;

use App\Services\UserContactPersistenceService;
use Illuminate\Support\Facades\DB;
use Modules\Member\Models\Member;

class MemberProfileService
{
    public function findForOrganization(
        int $organizationId,
        int $userId,
    ): Member {
        return Member::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->with([
                'user.details',
                'user.phoneNumbers',
                'user.emergencyContacts',
                'unit',
            ])
            ->firstOrFail();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(
        Member $member,
        array $data,
    ): Member {
        return DB::transaction(function () use ($member, $data) {
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

            if (array_key_exists('date_of_birth', $data)) {
                $member->user->details()->updateOrCreate(
                    [],
                    [
                        'date_of_birth' => $data['date_of_birth'],
                    ],
                );
            }

            if (array_key_exists('phone_numbers', $data) || array_key_exists('emergency_contacts', $data)) {
                app(UserContactPersistenceService::class)->syncForProfile(
                    user: $member->user,
                    phoneNumbers: array_key_exists('phone_numbers', $data) ? $data['phone_numbers'] : null,
                    emergencyContacts: array_key_exists('emergency_contacts', $data) ? $data['emergency_contacts'] : null,
                );
            }

            return $member->refresh()->load([
                'user.details',
                'user.phoneNumbers',
                'user.emergencyContacts',
                'unit',
            ]);
        });
    }
}