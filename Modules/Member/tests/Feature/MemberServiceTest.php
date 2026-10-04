<?php

namespace Modules\Member\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Member\Models\Member;
use Modules\Member\Services\MemberService;
use Tests\TestCase;

class MemberServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_a_member(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $service = app(MemberService::class);

        $member = $service->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'M-0001',
            'date_of_birth' => '1990-01-15',
            'joined_at' => '2026-09-23',
            'metadata' => [],
        ]);

        $this->assertInstanceOf(Member::class, $member);

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'M-0001',
        ]);
    }

    public function test_it_persists_structured_user_details_when_creating_a_member(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create([
            'name' => 'John Doe',
        ]);

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $service = app(MemberService::class);

        $member = $service->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'M-0008',
            'first_name' => 'John',
            'middle_name' => 'Michael',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-15',
            'joined_at' => '2026-09-23',
            'metadata' => [],
        ]);

        $this->assertDatabaseHas('user_details', [
            'user_id' => $user->id,
            'first_name' => 'John',
            'middle_name' => 'Michael',
            'last_name' => 'Doe',
        ]);

        $this->assertSame(
            'John',
            $member->user->details->first_name
        );

        $this->assertSame(
            'Michael',
            $member->user->details->middle_name
        );

        $this->assertSame(
            'Doe',
            $member->user->details->last_name
        );

        $this->assertSame(
            '1990-01-15',
            $member->user->details->date_of_birth->format('Y-m-d')
        );
    }

    public function test_it_allows_middle_and_last_name_to_be_omitted_when_creating_a_member(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $service = app(MemberService::class);

        $service->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'M-0009',
            'first_name' => 'John',
            'date_of_birth' => '1990-01-15',
            'joined_at' => '2026-09-23',
            'metadata' => [],
        ]);

        $this->assertDatabaseHas('user_details', [
            'user_id' => $user->id,
            'first_name' => 'John',
            'middle_name' => null,
            'last_name' => null,
        ]);

        $this->assertSame(
            '1990-01-15',
            $user->fresh()->details->date_of_birth->format('Y-m-d')
        );
    }

    public function test_it_updates_structured_user_details(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $service = app(MemberService::class);

        $member = $service->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'M-0010',
            'first_name' => 'John',
            'middle_name' => 'Michael',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-15',
            'joined_at' => '2026-09-23',
            'metadata' => [],
        ]);

        $service->update($member, [
            'first_name' => 'Jane',
            'middle_name' => 'Marie',
            'last_name' => 'Doe',
            'date_of_birth' => '1992-05-20',
        ]);

        $this->assertDatabaseHas('user_details', [
            'user_id' => $user->id,
            'first_name' => 'Jane',
            'middle_name' => 'Marie',
            'last_name' => 'Doe',
        ]);

        $this->assertSame(
            '1992-05-20',
            $member->fresh()->user->details->date_of_birth->format('Y-m-d')
        );
    }

    public function test_it_rejects_a_user_who_is_not_a_member_of_the_organization(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        $service = app(MemberService::class);

        $this->expectException(ValidationException::class);

        $service->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'M-0002',
            'date_of_birth' => '1992-03-10',
            'joined_at' => '2026-09-23',
            'metadata' => [],
        ]);
    }

    public function test_it_rejects_a_unit_from_another_organization(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();

        $unit = Unit::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $service = app(MemberService::class);

        $this->expectException(ValidationException::class);

        $service->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'unit_id' => $unit->id,
            'membership_number' => 'M-0003',
            'date_of_birth' => '1992-03-10',
            'joined_at' => '2026-09-23',
            'metadata' => [],
        ]);
    }

    public function test_membership_number_must_be_unique_within_an_organization(): void
    {
        $organization = Organization::factory()->create();

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $firstUser->id,
            'status' => 'active',
        ]);

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $secondUser->id,
            'status' => 'active',
        ]);

        $service = app(MemberService::class);

        $service->create([
            'organization_id' => $organization->id,
            'user_id' => $firstUser->id,
            'membership_number' => 'M-0004',
            'date_of_birth' => '1990-01-15',
            'joined_at' => '2026-09-23',
            'metadata' => [],
        ]);

        $this->expectException(ValidationException::class);

        $service->create([
            'organization_id' => $organization->id,
            'user_id' => $secondUser->id,
            'membership_number' => 'M-0004',
            'date_of_birth' => '1992-03-10',
            'joined_at' => '2026-09-23',
            'metadata' => [],
        ]);
    }

    public function test_the_same_membership_number_can_be_used_in_different_organizations(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $firstUser->id,
            'status' => 'active',
        ]);

        OrganizationMembership::create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $secondUser->id,
            'status' => 'active',
        ]);

        $service = app(MemberService::class);

        $firstMember = $service->create([
            'organization_id' => $organization->id,
            'user_id' => $firstUser->id,
            'membership_number' => 'M-0005',
            'date_of_birth' => '1990-01-15',
            'joined_at' => '2026-09-23',
            'metadata' => [],
        ]);

        $secondMember = $service->create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $secondUser->id,
            'membership_number' => 'M-0005',
            'date_of_birth' => '1992-03-10',
            'joined_at' => '2026-09-23',
            'metadata' => [],
        ]);

        $this->assertNotSame($firstMember->id, $secondMember->id);
    }

    public function test_a_user_can_only_have_one_member_record_per_organization(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $service = app(MemberService::class);

        $service->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'M-0006',
            'date_of_birth' => '1990-01-15',
            'joined_at' => '2026-09-23',
            'metadata' => [],
        ]);

        $this->expectException(ValidationException::class);

        $service->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'M-0007',
            'date_of_birth' => '1990-01-15',
            'joined_at' => '2026-09-23',
            'metadata' => [],
        ]);
    }

    public function test_it_persists_gender_in_user_details(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $service = app(MemberService::class);

        $member = $service->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'M-0011',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-15',
            'gender' => 'male',
            'joined_at' => '2026-09-23',
            'metadata' => [],
        ]);

        $this->assertDatabaseHas('user_details', [
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
        ]);

        $this->assertSame(
            'male',
            $member->user->details->gender
        );
    }

    public function test_it_persists_contact_details_in_user_details(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create([
            'phone' => '+94771234567',
        ]);

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $service = app(MemberService::class);

        $member = $service->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'M-0012',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'whatsapp' => '771234567',
            'whatsapp_calling_code' => '+94',
            'home_contact' => '0112345678',
            'joined_at' => '2026-09-23',
            'metadata' => [],
        ]);

        $this->assertDatabaseHas('user_details', [
            'user_id' => $user->id,
            'whatsapp' => '771234567',
            'whatsapp_calling_code' => '+94',
            'home_contact' => '0112345678',
        ]);

        $this->assertSame(
            '771234567',
            $member->user->details->whatsapp
        );

        $this->assertSame(
            '+94',
            $member->user->details->whatsapp_calling_code
        );

        $this->assertSame(
            '0112345678',
            $member->user->details->home_contact
        );

        // Primary phone remains on users and is not duplicated in user_details.
        $this->assertSame(
            '+94771234567',
            $member->user->phone
        );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => '+94771234567',
        ]);
    }

    public function test_it_persists_profession_and_company_in_user_details(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $service = app(MemberService::class);

        $member = $service->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'M-0013',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'profession' => 'Software Engineer',
            'company' => 'Example Technologies',
            'joined_at' => '2026-09-23',
            'metadata' => [],
        ]);

        $this->assertDatabaseHas('user_details', [
            'user_id' => $user->id,
            'profession' => 'Software Engineer',
            'company' => 'Example Technologies',
        ]);

        $this->assertSame(
            'Software Engineer',
            $member->user->details->profession
        );

        $this->assertSame(
            'Example Technologies',
            $member->user->details->company
        );
    }
}