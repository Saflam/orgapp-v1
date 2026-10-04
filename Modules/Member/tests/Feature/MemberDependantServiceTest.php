<?php

namespace Modules\Member\Tests\Feature;

use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberDependant;
use Modules\Member\Services\MemberDependantService;
use Tests\TestCase;

class MemberDependantServiceTest extends TestCase
{
    use RefreshDatabase;

    private MemberDependantService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MemberDependantService::class);
    }

    public function test_it_can_create_a_dependant(): void
    {
        $organization = Organization::factory()->create();

        $dependant = $this->service->create(
            organizationId: $organization->id,
            data: [
                'first_name' => 'Mary',
                'middle_name' => null,
                'last_name' => 'Smith',
                'date_of_birth' => '1990-05-10 00:00:00',
                'gender' => 'female',
            ],
        );

        $this->assertInstanceOf(MemberDependant::class, $dependant);

        $this->assertDatabaseHas('member_dependants', [
            'id' => $dependant->id,
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
            'last_name' => 'Smith',
            'date_of_birth' => '1990-05-10 00:00:00',
            'gender' => 'female',
            'converted_member_id' => null,
            'converted_at' => null,
        ]);
    }

    public function test_it_can_update_a_dependant(): void
    {
        $organization = Organization::factory()->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
            'last_name' => 'Smith',
            'date_of_birth' => '1990-05-10 00:00:00',
            'gender' => 'female',
        ]);

        $updated = $this->service->update(
            dependant: $dependant,
            data: [
                'first_name' => 'Mary Jane',
                'last_name' => 'Smith',
                'date_of_birth' => '1990-06-15 00:00:00',
                'gender' => 'female',
            ],
        );

        $this->assertSame($dependant->id, $updated->id);

        $this->assertDatabaseHas('member_dependants', [
            'id' => $dependant->id,
            'first_name' => 'Mary Jane',
            'date_of_birth' => '1990-06-15 00:00:00',
        ]);
    }

    public function test_it_rejects_updating_a_converted_dependant(): void
    {
        $organization = Organization::factory()->create();

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
            'last_name' => 'Smith',
            'converted_member_id' => $member->id,
            'converted_at' => Carbon::parse('2026-01-15'),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'A converted dependant cannot be updated.'
        );

        $this->service->update(
            dependant: $dependant,
            data: [
                'first_name' => 'Changed',
            ],
        );
    }

    public function test_it_can_mark_a_dependant_as_converted(): void
    {
        $organization = Organization::factory()->create();

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
            'last_name' => 'Smith',
        ]);

        $converted = $this->service->markAsConverted(
            dependant: $dependant,
            member: $member,
        );

        $this->assertSame($member->id, $converted->converted_member_id);
        $this->assertNotNull($converted->converted_at);

        $this->assertDatabaseHas('member_dependants', [
            'id' => $dependant->id,
            'converted_member_id' => $member->id,
        ]);
    }

    public function test_it_rejects_converting_an_already_converted_dependant(): void
    {
        $organization = Organization::factory()->create();

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
            'last_name' => 'Smith',
            'converted_member_id' => $member->id,
            'converted_at' => Carbon::parse('2026-01-15'),
        ]);

        $anotherMember = Member::factory()
            ->forOrganization($organization)
            ->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'This dependant has already been converted to a member.'
        );

        $this->service->markAsConverted(
            dependant: $dependant,
            member: $member,
        );
    }

    public function test_it_rejects_converting_a_dependant_to_a_member_from_another_organization(): void
    {
        $organization = Organization::factory()->create();

        $otherOrganization = Organization::factory()->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
            'last_name' => 'Smith',
        ]);

        $member = Member::factory()
            ->forOrganization($otherOrganization)
            ->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'The dependant and member must belong to the same organization.'
        );

        $this->service->markAsConverted(
            dependant: $dependant,
            member: $member,
        );
    }

    public function test_it_can_mark_a_dependant_as_converted_to_a_member(): void
    {
        $organization = Organization::factory()->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
            'last_name' => 'Smith',
        ]);

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $result = $this->service->markAsConverted(
            dependant: $dependant,
            member: $member,
        );

        $this->assertSame(
            $member->id,
            $result->converted_member_id
        );

        $this->assertNotNull($result->converted_at);

        $this->assertDatabaseHas('member_dependants', [
            'id' => $dependant->id,
            'converted_member_id' => $member->id,
        ]);
    }

    public function test_it_cannot_convert_an_already_converted_dependant(): void
    {
        $organization = Organization::factory()->create();

        $firstMember = Member::factory()
            ->forOrganization($organization)
            ->create();

        $secondMember = Member::factory()
            ->forOrganization($organization)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
            'converted_member_id' => $firstMember->id,
            'converted_at' => '2026-01-01',
        ]);

        $this->expectException(\DomainException::class);

        $this->expectExceptionMessage(
            'This dependant has already been converted to a member.'
        );

        $this->service->markAsConverted(
            dependant: $dependant,
            member: $secondMember,
        );
    }

    public function test_it_cannot_convert_a_dependant_to_a_member_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organizationA->id,
            'first_name' => 'Mary',
        ]);

        $member = Member::factory()
            ->forOrganization($organizationB)
            ->create();

        $this->expectException(\DomainException::class);

        $this->expectExceptionMessage(
            'The dependant and member must belong to the same organization.'
        );

        $this->service->markAsConverted(
            dependant: $dependant,
            member: $member,
        );
    }
}