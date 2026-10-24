<?php

namespace Modules\Saradhi\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Member\Models\Member;
use Modules\Saradhi\Models\SaradhiMemberProfile;
use Modules\Saradhi\Services\SaradhiMemberProfileSectionExtension;
use Tests\TestCase;

class SaradhiMemberProfileSectionExtensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_saradhi_profile_fields_for_the_member_profile_section(): void
    {
        $organization = Organization::factory()->create();
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Aluva Unit',
        ]);
        $member = Member::factory()->create([
            'organization_id' => $organization->id,
        ]);

        SaradhiMemberProfile::query()->create([
            'member_id' => $member->id,
            'governorate' => 'Ernakulam',
            'sndp_branch' => 'Aluva',
            'sndp_branch_number' => 'SNDP-42',
            'sndp_union' => 'Aluva Union',
            'introducer_name' => 'Anil Kumar',
            'introducer_calling_code' => '+91',
            'introducer_phone' => '9876543210',
            'introducer_mid' => 'MID-1001',
            'introducer_unit_id' => $unit->id,
        ]);

        $extension = new SaradhiMemberProfileSectionExtension();
        $data = $extension->data($organization, $member);

        $this->assertNotNull($data);
        $this->assertSame('Saradhi', $extension->module());
        $this->assertSame('Saradhi details', $extension->title());
        $this->assertSame('saradhi::organization.member-profile-section', $extension->view());
        $this->assertSame([
            ['label' => 'Governorate', 'value' => 'Ernakulam'],
            ['label' => 'SNDP branch', 'value' => 'Aluva'],
            ['label' => 'SNDP branch number', 'value' => 'SNDP-42'],
            ['label' => 'SNDP union', 'value' => 'Aluva Union'],
            ['label' => 'Introducer name', 'value' => 'Anil Kumar'],
            ['label' => 'Introducer phone', 'value' => '+91 9876543210'],
            ['label' => 'Introducer MID', 'value' => 'MID-1001'],
            ['label' => 'Introducer unit', 'value' => 'Aluva Unit'],
        ], $data['fields']);
    }

    public function test_it_returns_an_editable_section_when_saradhi_details_are_not_yet_recorded(): void
    {
        $organization = Organization::factory()->create();
        $member = Member::factory()->create(['organization_id' => $organization->id]);

        $data = (new SaradhiMemberProfileSectionExtension())->data($organization, $member);

        $this->assertNotNull($data);
        $this->assertSame([], $data['fields']);
        $this->assertFalse($data['profile']->exists);
        $this->assertSame('saradhi::organization.member-profile-section-edit', (new SaradhiMemberProfileSectionExtension())->editView());
    }

    public function test_it_updates_saradhi_profile_fields_for_the_current_member(): void
    {
        $organization = Organization::factory()->create();
        $member = Member::factory()->create(['organization_id' => $organization->id]);
        $request = Request::create('/member/profile/modules/Saradhi', 'PUT', [
            'governorate' => 'Ernakulam',
            'sndp_branch' => 'Aluva',
            'sndp_branch_number' => 'SNDP-42',
            'sndp_union' => 'Aluva Union',
            'introducer_name' => 'Anil Kumar',
            'introducer_calling_code' => '+91',
            'introducer_phone' => '9876543210',
            'introducer_mid' => 'MID-1001',
            'introducer_unit_id' => null,
        ]);

        (new SaradhiMemberProfileSectionExtension())->update($request, $organization, $member);

        $this->assertDatabaseHas('saradhi_member_profiles', [
            'member_id' => $member->id,
            'governorate' => 'Ernakulam',
            'sndp_branch' => 'Aluva',
            'introducer_name' => 'Anil Kumar',
            'introducer_phone' => '9876543210',
        ]);
    }

    public function test_it_rejects_an_introducer_unit_from_another_organization(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $unit = Unit::factory()->create(['organization_id' => $otherOrganization->id]);
        $member = Member::factory()->create(['organization_id' => $organization->id]);
        $request = Request::create('/member/profile/modules/Saradhi', 'PUT', [
            'introducer_unit_id' => $unit->id,
        ]);

        try {
            (new SaradhiMemberProfileSectionExtension())->update($request, $organization, $member);
            $this->fail('Expected a validation exception for a unit from another organization.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('introducer_unit_id', $exception->errors());
        }
    }

    public function test_introducer_unit_is_not_exposed_from_another_organization(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $unit = Unit::factory()->create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Private Other Unit',
        ]);
        $member = Member::factory()->create([
            'organization_id' => $organization->id,
        ]);
        SaradhiMemberProfile::query()->create([
            'member_id' => $member->id,
            'governorate' => 'Ernakulam',
            'introducer_unit_id' => $unit->id,
        ]);

        $data = (new SaradhiMemberProfileSectionExtension())->data($organization, $member);

        $this->assertNotNull($data);
        $this->assertSame([
            ['label' => 'Governorate', 'value' => 'Ernakulam'],
        ], $data['fields']);
    }
}
