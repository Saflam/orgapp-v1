<?php

namespace Modules\Saradhi\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Member\Contracts\MemberProfileSectionExtension;
use Modules\Member\Models\Member;
use Modules\Saradhi\Models\SaradhiMemberProfile;

class SaradhiMemberProfileSectionExtension implements MemberProfileSectionExtension
{
    public function module(): string
    {
        return 'Saradhi';
    }

    public function title(): string
    {
        return 'Saradhi details';
    }

    public function view(): string
    {
        return 'saradhi::organization.member-profile-section';
    }

    public function editView(): string
    {
        return 'saradhi::organization.member-profile-section-edit';
    }

    public function data(Organization $organization, Member $member): ?array
    {
        $profile = SaradhiMemberProfile::query()->firstOrNew(['member_id' => $member->id]);
        $introducerUnitName = null;

        if ($profile->introducer_unit_id) {
            $introducerUnitName = Unit::query()
                ->where('organization_id', $organization->id)
                ->whereKey($profile->introducer_unit_id)
                ->value('name');
        }

        $introducerPhone = trim(implode(' ', array_filter([
            $profile->introducer_calling_code,
            $profile->introducer_phone,
        ], static fn ($value): bool => filled($value))));

        $fields = collect([
            ['label' => 'Governorate', 'value' => $profile->governorate],
            ['label' => 'SNDP branch', 'value' => $profile->sndp_branch],
            ['label' => 'SNDP branch number', 'value' => $profile->sndp_branch_number],
            ['label' => 'SNDP union', 'value' => $profile->sndp_union],
            ['label' => 'Introducer name', 'value' => $profile->introducer_name],
            ['label' => 'Introducer phone', 'value' => $introducerPhone],
            ['label' => 'Introducer MID', 'value' => $profile->introducer_mid],
            ['label' => 'Introducer unit', 'value' => $introducerUnitName],
        ])->filter(static fn (array $field): bool => filled($field['value']))->values()->all();

        $units = Unit::query()
            ->where('organization_id', $organization->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'fields' => $fields,
            'profile' => $profile,
            'units' => $units,
            'member' => $member,
        ];
    }

    public function update(Request $request, Organization $organization, Member $member): void
    {
        abort_unless((int) $member->organization_id === (int) $organization->id, 404);

        $validated = $request->validate([
            'governorate' => ['nullable', 'string', 'max:255'],
            'sndp_branch' => ['nullable', 'string', 'max:255'],
            'sndp_branch_number' => ['nullable', 'string', 'max:255'],
            'sndp_union' => ['nullable', 'string', 'max:255'],
            'introducer_name' => ['nullable', 'string', 'max:255'],
            'introducer_calling_code' => ['nullable', 'string', 'max:8', 'regex:/^\+?[0-9]{1,4}$/'],
            'introducer_phone' => ['nullable', 'string', 'max:30'],
            'introducer_mid' => ['nullable', 'string', 'max:255'],
            'introducer_unit_id' => [
                'nullable',
                'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('organization_id', $organization->id)),
            ],
        ]);

        SaradhiMemberProfile::query()->updateOrCreate(
            ['member_id' => $member->id],
            $validated,
        );
    }
}
