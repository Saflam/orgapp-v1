<?php

namespace Modules\Member\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\IdentificationType;
use Modules\Core\Models\Organization;
use Modules\Member\Enums\MembershipStatus;
use Modules\Member\Models\Member;
use Modules\Member\Models\Membership;
use Modules\Member\Models\MembershipType;
use Modules\Member\Models\UserIdentification;
use Modules\Member\Services\MemberRegistrationService;
use Modules\Member\Services\MembershipApplicationExtensionRegistry;
use Tests\TestCase;

class MemberRegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_a_member_with_an_in_review_membership(): void
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

        $membershipType = MembershipType::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Primary Member',
            'code' => 'PRIMARY',
            'description' => null,
            'is_active' => true,
            'metadata' => [],
        ]);

        $service = app(MemberRegistrationService::class);

        $member = $service->register([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'M-0005',
            'date_of_birth' => '1990-01-15',
            'joined_at' => '2026-09-23',
            'metadata' => [],
        ], $membershipType->id, '2026-09-23');

        $this->assertInstanceOf(Member::class, $member);

        $this->assertSame(
            $user->id,
            $member->user_id
        );

        $this->assertTrue(
            $member->user->is($user)
        );

        $membership = Membership::query()
            ->where('member_id', $member->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertSame(
            $membershipType->id,
            $membership->membership_type_id
        );
        $this->assertSame(
            MembershipStatus::IN_REVIEW,
            $membership->status
        );
        $this->assertSame(
            '2026-09-23',
            $membership->starts_at->toDateString()
        );
        $this->assertNull($membership->ends_at);

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'M-0005',
        ]);

        $this->assertDatabaseHas('memberships', [
            'member_id' => $member->id,
            'membership_type_id' => $membershipType->id,
            'status' => MembershipStatus::IN_REVIEW->value,
        ]);
    }

    public function test_membership_type_must_belong_to_the_member_organization(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $membershipType = MembershipType::query()->create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Primary Member',
            'code' => 'PRIMARY',
            'description' => null,
            'is_active' => true,
            'metadata' => [],
        ]);

        $service = app(MemberRegistrationService::class);

        $this->expectException(ValidationException::class);

        $service->register([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'M-0006',
            'date_of_birth' => '1992-03-10',
            'joined_at' => '2026-09-23',
            'metadata' => [],
        ], $membershipType->id, '2026-09-23');
    }

    public function test_member_is_not_created_when_membership_type_is_invalid(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $service = app(MemberRegistrationService::class);

        $this->expectException(ValidationException::class);

        $service->register([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'M-0007',
            'date_of_birth' => '1995-01-01',
            'joined_at' => '2026-09-23',
            'metadata' => [],
        ], 999999, '2026-09-23');

        $this->assertDatabaseMissing('members', [
            'organization_id' => $organization->id,
            'membership_number' => 'M-0007',
        ]);
    }

    public function test_member_cannot_be_registered_for_a_user_outside_the_organization(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $membershipType = MembershipType::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Primary Member',
            'code' => 'PRIMARY',
            'description' => null,
            'is_active' => true,
            'metadata' => [],
        ]);

        $service = app(MemberRegistrationService::class);

        $this->expectException(ValidationException::class);

        $service->register([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'M-0008',
            'date_of_birth' => '1995-01-01',
            'joined_at' => '2026-09-23',
            'metadata' => [],
        ], $membershipType->id, '2026-09-23');

        $this->assertDatabaseMissing('members', [
            'organization_id' => $organization->id,
            'membership_number' => 'M-0008',
        ]);
    }

    public function test_it_registers_a_member_with_an_address(): void
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

        $membershipType = MembershipType::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Primary Member',
            'code' => 'PRIMARY',
            'description' => null,
            'is_active' => true,
            'metadata' => [],
        ]);

        $service = app(MemberRegistrationService::class);

        $member = $service->register(
            [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'membership_number' => 'M-0009',
                'date_of_birth' => '1990-01-15',
                'joined_at' => '2026-09-23',
                'metadata' => [],
            ],
            $membershipType->id,
            '2026-09-23',
            [],
            [
                'address_type' => 'home',
                'address_line_1' => '123 Main Road',
                'address_line_2' => 'Near Temple',
                'locality' => 'Edappally',
                'city' => 'Kochi',
                'state' => 'Kerala',
                'postal_code' => '682024',
                'country' => 'India',
                'is_primary' => true,
                'metadata' => [
                    'landmark' => 'Near Temple',
                ],
            ],
        );

        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $user->id,
            'address_type' => 'home',
            'address_line_1' => '123 Main Road',
            'city' => 'Kochi',
            'state' => 'Kerala',
            'postal_code' => '682024',
            'country' => 'India',
            'is_primary' => true,
        ]);

        $this->assertCount(
            1,
            UserAddress::query()
                ->where('user_id', $user->id)
                ->get()
        );

        $this->assertTrue(
            $member->user->addresses->first()->is_primary
        );
    }

    public function test_it_persists_personal_details_when_registering_a_member(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $membershipType = MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Regular Membership',
            'code' => 'REGULAR',
        ]);

        $service = app(MemberRegistrationService::class);

        $member = $service->register(
            memberData: [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'membership_number' => 'M-0020',
                'first_name' => 'John',
                'middle_name' => 'Michael',
                'last_name' => 'Doe',
                'date_of_birth' => '1990-01-15',
                'gender' => 'male',
                'whatsapp' => '771234567',
                'whatsapp_calling_code' => '+94',
                'home_contact' => '0112345678',
                'profession' => 'Software Engineer',
                'company' => 'Example Technologies',
                'joined_at' => '2026-10-03',
                'metadata' => [],
            ],
            membershipTypeId: $membershipType->id,
            startsAt: '2026-10-03',
        );

        $this->assertDatabaseHas('user_details', [
            'user_id' => $user->id,
            'first_name' => 'John',
            'middle_name' => 'Michael',
            'last_name' => 'Doe',
            'gender' => 'male',
            'whatsapp' => '771234567',
            'whatsapp_calling_code' => '+94',
            'home_contact' => '0112345678',
            'profession' => 'Software Engineer',
            'company' => 'Example Technologies',
        ]);

        $this->assertSame(
            'John',
            $member->user->details->first_name
        );

        $this->assertSame(
            'Doe',
            $member->user->details->last_name
        );

        $this->assertSame(
            'Software Engineer',
            $member->user->details->profession
        );

        $this->assertSame(
            'Example Technologies',
            $member->user->details->company
        );
    }

    public function test_it_persists_the_application_address(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $membershipType = MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Regular Membership',
            'code' => 'REGULAR',
        ]);

        $service = app(MemberRegistrationService::class);

        $member = $service->register(
            memberData: [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'membership_number' => 'M-0021',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'joined_at' => '2026-10-03',
                'metadata' => [],
            ],
            membershipTypeId: $membershipType->id,
            startsAt: '2026-10-03',
            addressData: [
                'address_type' => 'home',
                'address_line_1' => '123 Main Street',
                'address_line_2' => 'Apartment 4B',
                'locality' => 'Central',
                'city' => 'Colombo',
                'state' => 'Western',
                'postal_code' => '00100',
                'country' => 'Sri Lanka',
                'is_primary' => true,
                'metadata' => [],
            ],
        );

        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $member->user_id,
            'address_type' => 'home',
            'address_line_1' => '123 Main Street',
            'city' => 'Colombo',
            'country' => 'Sri Lanka',
        ]);
    }

    public function test_it_persists_identifications_during_registration(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        /*
         * The registration service requires the user to already
         * belong to the organization.
         */
        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $membershipType = MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Regular Membership',
            'code' => 'REGULAR',
        ]);

        $identificationType = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        $service = app(MemberRegistrationService::class);

        $member = $service->register(
            memberData: [
                'user_id' => $user->id,
                'organization_id' => $organization->id,
                'first_name' => 'John',
                'middle_name' => null,
                'last_name' => 'Doe',
                'membership_number' => 'MEM-001',
            ],
            membershipTypeId: $membershipType->id,
            startsAt: '2026-10-03',
            identificationData: [
                [
                    'identification_type' => $identificationType,
                    'identification_number' => 'CIV-123456',
                    'metadata' => [
                        'source' => 'membership_application',
                    ],
                ],
            ],
        );

        $this->assertDatabaseHas(
            'user_identifications',
            [
                'user_id' => $user->id,
                'identification_type_id' => $identificationType->id,
                'identification_number' => 'CIV-123456',
            ]
        );

        $this->assertNotNull($member);
    }

    public function test_it_can_persist_multiple_identifications_during_registration(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        /*
         * The registration service requires the user to already
         * belong to the organization.
         */
        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $membershipType = MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Regular Membership',
            'code' => 'REGULAR',
        ]);

        $civilId = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        $passport = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        $service = app(MemberRegistrationService::class);

        $service->register(
            memberData: [
                'user_id' => $user->id,
                'organization_id' => $organization->id,
                'first_name' => 'John',
                'middle_name' => null,
                'last_name' => 'Doe',
                'membership_number' => 'MEM-002',
            ],
            membershipTypeId: $membershipType->id,
            startsAt: '2026-10-03',
            identificationData: [
                [
                    'identification_type' => $civilId,
                    'identification_number' => 'CIV-123456',
                ],
                [
                    'identification_type' => $passport,
                    'identification_number' => 'P1234567',
                ],
            ],
        );

        $this->assertDatabaseCount(
            'user_identifications',
            2
        );

        $this->assertDatabaseHas(
            'user_identifications',
            [
                'user_id' => $user->id,
                'identification_type_id' => $civilId->id,
                'identification_number' => 'CIV-123456',
            ]
        );

        $this->assertDatabaseHas(
            'user_identifications',
            [
                'user_id' => $user->id,
                'identification_type_id' => $passport->id,
                'identification_number' => 'P1234567',
            ]
        );
    }

    public function test_it_persists_identification_document_during_registration(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $membershipType = MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Regular Membership',
            'code' => 'REGULAR',
        ]);

        $civilId = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        $service = app(MemberRegistrationService::class);

        $service->register(
            memberData: [
                'user_id' => $user->id,
                'organization_id' => $organization->id,
                'first_name' => 'John',
                'middle_name' => null,
                'last_name' => 'Doe',
                'membership_number' => 'MEM-003',
            ],
            membershipTypeId: $membershipType->id,
            startsAt: '2026-10-03',
            identificationData: [
                [
                    'identification_type' => $civilId,
                    'identification_number' => 'CIV-123456',
                    'documents' => [
                        'document' => UploadedFile::fake()->create(
                            'civil-id.pdf',
                            100,
                            'application/pdf',
                        ),
                    ],
                ],
            ],
        );

        $identification = UserIdentification::query()
            ->where('user_id', $user->id)
            ->where('identification_type_id', $civilId->id)
            ->firstOrFail();

        $this->assertSame(
            'CIV-123456',
            $identification->identification_number,
        );

        $this->assertCount(
            1,
            $identification->media()
                ->where('collection_name', 'identification-documents')
                ->get(),
        );

        $media = $identification->media()
            ->where('collection_name', 'identification-documents')
            ->firstOrFail();

        $this->assertSame(
            'document',
            $media->getCustomProperty('document_side'),
        );

        $this->assertSame(
            'civil-id.pdf',
            $media->file_name,
        );
    }

    public function test_it_persists_front_and_back_identification_documents_during_registration(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $membershipType = MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Regular Membership',
            'code' => 'REGULAR',
        ]);

        $civilId = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        $service = app(MemberRegistrationService::class);

        $service->register(
            memberData: [
                'user_id' => $user->id,
                'organization_id' => $organization->id,
                'first_name' => 'John',
                'middle_name' => null,
                'last_name' => 'Doe',
                'membership_number' => 'MEM-004',
            ],
            membershipTypeId: $membershipType->id,
            startsAt: '2026-10-03',
            identificationData: [
                [
                    'identification_type' => $civilId,
                    'identification_number' => 'CIV-654321',
                    'documents' => [
                        'front' => UploadedFile::fake()->create(
                            'civil-id-front.pdf',
                            100,
                            'application/pdf',
                        ),
                        'back' => UploadedFile::fake()->create(
                            'civil-id-back.pdf',
                            100,
                            'application/pdf',
                        ),
                    ],
                ],
            ],
        );

        $identification = UserIdentification::query()
            ->where('user_id', $user->id)
            ->where('identification_type_id', $civilId->id)
            ->firstOrFail();

        $media = $identification->media()
            ->where('collection_name', 'identification-documents')
            ->get();

        $this->assertCount(2, $media);

        $front = $media->first(
            fn ($item) =>
                $item->getCustomProperty('document_side') === 'front'
        );

        $back = $media->first(
            fn ($item) =>
                $item->getCustomProperty('document_side') === 'back'
        );

        $this->assertNotNull($front);
        $this->assertNotNull($back);

        $this->assertSame(
            'civil-id-front.pdf',
            $front->file_name,
        );

        $this->assertSame(
            'civil-id-back.pdf',
            $back->file_name,
        );
    }

    public function test_it_rolls_back_registration_when_extension_persistence_fails(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $membershipType = MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Regular Membership',
            'code' => 'REGULAR',
        ]);

        $extensionRegistry = $this->mock(
            MembershipApplicationExtensionRegistry::class,
            function ($mock): void {
                $mock
                    ->shouldReceive('persist')
                    ->once()
                    ->andThrow(
                        new \RuntimeException(
                            'Extension persistence failed.'
                        )
                    );
            }
        );

        $service = app(MemberRegistrationService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Extension persistence failed.'
        );

        try {
            $service->register(
                memberData: [
                    'user_id' => $user->id,
                    'organization_id' => $organization->id,
                    'first_name' => 'John',
                    'middle_name' => null,
                    'last_name' => 'Doe',
                    'membership_number' => 'MEM-ROLLBACK',
                    'date_of_birth' => '1990-01-15',
                    'joined_at' => '2026-10-03',
                    'metadata' => [],
                ],
                membershipTypeId: $membershipType->id,
                startsAt: '2026-10-03',
                extensionData: [
                    'Saradhi' => [
                        'test_field' => 'test-value',
                    ],
                ],
            );
        } finally {
            /*
             * The assertions are deliberately executed after the
             * transaction has been rolled back.
             */
            $this->assertDatabaseMissing('members', [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'membership_number' => 'MEM-ROLLBACK',
            ]);

            $this->assertDatabaseMissing('memberships', [
                'membership_type_id' => $membershipType->id,
            ]);
        }

        unset($extensionRegistry);
    }

    public function test_it_rolls_back_registration_and_cleans_up_documents_when_extension_persistence_fails(): void
    {
        Storage::fake(config('media-library.disk_name'));

        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $membershipType = MembershipType::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Regular Membership',
            'code' => 'REGULAR',
        ]);

        $civilId = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        $extensionRegistry = $this->mock(
            MembershipApplicationExtensionRegistry::class,
            function ($mock): void {
                $mock
                    ->shouldReceive('persist')
                    ->once()
                    ->andThrow(
                        new \RuntimeException(
                            'Extension persistence failed.'
                        )
                    );
            }
        );

        $service = app(MemberRegistrationService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Extension persistence failed.'
        );

        try {
            $service->register(
                memberData: [
                    'organization_id' => $organization->id,
                    'user_id' => $user->id,
                    'first_name' => 'John',
                    'middle_name' => null,
                    'last_name' => 'Doe',
                    'membership_number' => 'MEM-FILE-ROLLBACK',
                    'date_of_birth' => '1990-01-15',
                    'joined_at' => '2026-10-03',
                    'metadata' => [],
                ],
                membershipTypeId: $membershipType->id,
                startsAt: '2026-10-03',
                identificationData: [
                    [
                        'identification_type' => $civilId,
                        'identification_number' => 'CIV-ROLLBACK',
                        'documents' => [
                            'document' => UploadedFile::fake()->create(
                                'civil-id-rollback.pdf',
                                100,
                                'application/pdf',
                            ),
                        ],
                    ],
                ],
                extensionData: [
                    'Saradhi' => [
                        'test_field' => 'test-value',
                    ],
                ],
            );
        } finally {
            /*
            * The database transaction has been rolled back at this point.
            */
            $this->assertDatabaseMissing('members', [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'membership_number' => 'MEM-FILE-ROLLBACK',
            ]);

            $this->assertDatabaseMissing('memberships', [
                'membership_type_id' => $membershipType->id,
            ]);

            $this->assertDatabaseMissing('user_identifications', [
                'user_id' => $user->id,
                'identification_type_id' => $civilId->id,
                'identification_number' => 'CIV-ROLLBACK',
            ]);

            $this->assertDatabaseCount('media', 0);

            Storage::disk(
                config('media-library.disk_name')
            )->assertDirectoryEmpty('');
        }

        unset($extensionRegistry);
    }
}