<?php

namespace Modules\Saradhi\Services;

use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Member\Contracts\MembershipApplicationExtension;
use Modules\Member\Models\Member;
use Modules\Saradhi\Models\SaradhiMemberProfile;

class SaradhiMembershipApplicationExtension implements MembershipApplicationExtension
{
    public function module(): string
    {
        return 'Saradhi';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function fields(): array
    {
        return [
            'governorate' => [
                'type' => 'string',
                'label' => 'Governorate',
                'required' => false,
            ],
            'sndp_branch' => [
                'type' => 'string',
                'label' => 'SNDP Branch',
                'required' => false,
            ],
            'sndp_branch_number' => [
                'type' => 'string',
                'label' => 'SNDP Branch Number',
                'required' => false,
            ],
            'sndp_union' => [
                'type' => 'string',
                'label' => 'SNDP Union',
                'required' => false,
            ],
            'introducer_name' => [
                'type' => 'string',
                'label' => 'Introducer Name',
                'required' => false,
            ],
            'introducer_calling_code' => [
                'type' => 'string',
                'label' => 'Introducer Calling Code',
                'required' => false,
            ],
            'introducer_phone' => [
                'type' => 'string',
                'label' => 'Introducer Phone',
                'required' => false,
            ],
            'introducer_mid' => [
                'type' => 'string',
                'label' => 'Introducer MID',
                'required' => false,
            ],
            'introducer_unit_id' => [
                'type' => 'integer',
                'label' => 'Introducer Unit',
                'required' => false,
            ],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'governorate' => [
                'nullable',
                'string',
                'max:255',
            ],
            'sndp_branch' => [
                'nullable',
                'string',
                'max:255',
            ],
            'sndp_branch_number' => [
                'nullable',
                'string',
                'max:255',
            ],
            'sndp_union' => [
                'nullable',
                'string',
                'max:255',
            ],
            'introducer_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'introducer_calling_code' => [
                'nullable',
                'string',
                'max:20',
            ],
            'introducer_phone' => [
                'nullable',
                'string',
                'max:50',
            ],
            'introducer_mid' => [
                'nullable',
                'string',
                'max:255',
            ],
            'introducer_unit_id' => [
                'nullable',
                'integer',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function persist(
        Organization $organization,
        Member $member,
        array $data,
    ): void {
        if ($data === []) {
            return;
        }

        $introducerUnitId = $data['introducer_unit_id'] ?? null;

        if ($introducerUnitId !== null) {
            $belongsToOrganization = Unit::query()
                ->whereKey($introducerUnitId)
                ->where(
                    'organization_id',
                    $organization->id
                )
                ->exists();

            if (! $belongsToOrganization) {
                throw ValidationException::withMessages([
                    'introducer_unit_id' =>
                        'The selected introducer unit does not belong to the selected organization.',
                ]);
            }
        }

        SaradhiMemberProfile::query()->updateOrCreate(
            [
                'member_id' => $member->id,
            ],
            [
                'governorate' =>
                    $data['governorate'] ?? null,

                'sndp_branch' =>
                    $data['sndp_branch'] ?? null,

                'sndp_branch_number' =>
                    $data['sndp_branch_number'] ?? null,

                'sndp_union' =>
                    $data['sndp_union'] ?? null,

                'introducer_name' =>
                    $data['introducer_name'] ?? null,

                'introducer_calling_code' =>
                    $data['introducer_calling_code'] ?? null,

                'introducer_phone' =>
                    $data['introducer_phone'] ?? null,

                'introducer_mid' =>
                    $data['introducer_mid'] ?? null,

                'introducer_unit_id' =>
                    $introducerUnitId,
            ],
        );
    }
}