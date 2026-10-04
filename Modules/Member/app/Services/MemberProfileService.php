<?php

namespace Modules\Member\Services;

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

            return $member->refresh()->load([
                'user.details',
                'unit',
            ]);
        });
    }
}