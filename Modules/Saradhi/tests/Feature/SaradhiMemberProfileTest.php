<?php

namespace Modules\Saradhi\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Member\Models\Member;
use Modules\Saradhi\Models\SaradhiMemberProfile;
use Tests\TestCase;

class SaradhiMemberProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_saradhi_profile_belongs_to_a_member(): void
    {
        $member = Member::factory()->create();

        $profile = SaradhiMemberProfile::query()->create([
            'member_id' => $member->id,
            'governorate' => 'Ernakulam',
            'sndp_branch' => 'Aluva',
            'sndp_branch_number' => '123',
            'sndp_union' => 'Aluva Union',
            'metadata' => [],
        ]);

        $this->assertTrue(
            $profile->member->is($member)
        );
    }

    public function test_introducer_unit_is_optional(): void
    {
        $member = Member::factory()->create();

        $profile = SaradhiMemberProfile::query()->create([
            'member_id' => $member->id,
            'introducer_name' => 'John Doe',
            'introducer_calling_code' => '+91',
            'introducer_phone' => '9876543210',
            'introducer_mid' => 'MID-1001',
            'introducer_unit_id' => null,
            'metadata' => [],
        ]);

        $this->assertNull(
            $profile->introducerUnit
        );
    }

    public function test_profile_can_reference_an_introducer_unit(): void
    {
        $organization = Organization::factory()->create();

        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $member = Member::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $profile = SaradhiMemberProfile::query()->create([
            'member_id' => $member->id,
            'introducer_unit_id' => $unit->id,
            'metadata' => [],
        ]);

        $this->assertTrue(
            $profile->introducerUnit->is($unit)
        );
    }

    public function test_deleting_member_deletes_saradhi_profile(): void
    {
        $member = Member::factory()->create();

        $profile = SaradhiMemberProfile::query()->create([
            'member_id' => $member->id,
            'governorate' => 'Ernakulam',
        ]);

        $member->delete();

        $this->assertDatabaseMissing(
            'saradhi_member_profiles',
            [
                'id' => $profile->id,
            ]
        );
    }

    public function test_a_member_can_have_only_one_saradhi_profile(): void
    {
        $member = Member::factory()->create();

        SaradhiMemberProfile::query()->create([
            'member_id' => $member->id,
        ]);

        $this->expectException(
            \Illuminate\Database\QueryException::class
        );

        SaradhiMemberProfile::query()->create([
            'member_id' => $member->id,
        ]);
    }
}