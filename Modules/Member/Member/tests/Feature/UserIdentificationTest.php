<?php

namespace Modules\Member\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Modules\Core\Models\IdentificationType;
use Modules\Member\Models\UserIdentification;
use Tests\TestCase;

class UserIdentificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_a_user_identification(): void
    {
        $user = User::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        $identification = UserIdentification::create([
            'user_id' => $user->id,
            'identification_type_id' => $identificationType->id,
            'identification_number' => 'CIV-123456',
            'metadata' => [
                'source' => 'membership_application',
            ],
        ]);

        $this->assertDatabaseHas(
            'user_identifications',
            [
                'id' => $identification->id,
                'user_id' => $user->id,
                'identification_type_id' => $identificationType->id,
                'identification_number' => 'CIV-123456',
            ]
        );

        $this->assertSame(
            ['source' => 'membership_application'],
            $identification->metadata
        );
    }

    public function test_it_belongs_to_a_user(): void
    {
        $user = User::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        $identification = UserIdentification::create([
            'user_id' => $user->id,
            'identification_type_id' => $identificationType->id,
            'identification_number' => 'P1234567',
        ]);

        $this->assertTrue(
            $identification->user->is($user)
        );
    }

    public function test_it_belongs_to_an_identification_type(): void
    {
        $user = User::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        $identification = UserIdentification::create([
            'user_id' => $user->id,
            'identification_type_id' => $identificationType->id,
            'identification_number' => 'P1234567',
        ]);

        $this->assertTrue(
            $identification->identificationType->is($identificationType)
        );
    }

    public function test_a_user_can_have_multiple_identification_types(): void
    {
        $user = User::factory()->create();

        $civilId = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        $passport = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        UserIdentification::create([
            'user_id' => $user->id,
            'identification_type_id' => $civilId->id,
            'identification_number' => 'CIV-123456',
        ]);

        UserIdentification::create([
            'user_id' => $user->id,
            'identification_type_id' => $passport->id,
            'identification_number' => 'P1234567',
        ]);

        $this->assertDatabaseCount(
            'user_identifications',
            2
        );
    }

    public function test_the_same_user_cannot_have_duplicate_identification_type(): void
    {
        $user = User::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        UserIdentification::create([
            'user_id' => $user->id,
            'identification_type_id' => $identificationType->id,
            'identification_number' => 'CIV-123456',
        ]);

        $this->expectException(QueryException::class);

        UserIdentification::create([
            'user_id' => $user->id,
            'identification_type_id' => $identificationType->id,
            'identification_number' => 'CIV-999999',
        ]);
    }

    public function test_different_users_can_use_the_same_identification_type(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        UserIdentification::create([
            'user_id' => $userA->id,
            'identification_type_id' => $identificationType->id,
            'identification_number' => 'CIV-111111',
        ]);

        UserIdentification::create([
            'user_id' => $userB->id,
            'identification_type_id' => $identificationType->id,
            'identification_number' => 'CIV-222222',
        ]);

        $this->assertDatabaseCount(
            'user_identifications',
            2
        );
    }

    public function test_identification_metadata_is_cast_to_array(): void
    {
        $user = User::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        $identification = UserIdentification::create([
            'user_id' => $user->id,
            'identification_type_id' => $identificationType->id,
            'identification_number' => 'P1234567',
            'metadata' => [
                'issued_country' => 'India',
                'notes' => 'Verified',
            ],
        ]);

        $identification->refresh();

        $this->assertIsArray(
            $identification->metadata
        );

        $this->assertSame(
            'India',
            $identification->metadata['issued_country']
        );

        $this->assertSame(
            'Verified',
            $identification->metadata['notes']
        );
    }

    public function test_identifications_are_deleted_when_the_user_is_deleted(): void
    {
        $user = User::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        $identification = UserIdentification::create([
            'user_id' => $user->id,
            'identification_type_id' => $identificationType->id,
            'identification_number' => 'CIV-123456',
        ]);

        $user->delete();

        $this->assertDatabaseMissing(
            'user_identifications',
            [
                'id' => $identification->id,
            ]
        );
    }

    public function test_identification_can_have_a_front_document(): void
    {
        $user = User::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        $identification = UserIdentification::create([
            'user_id' => $user->id,
            'identification_type_id' => $identificationType->id,
            'identification_number' => 'CIV-123456',
        ]);

        $media = $identification
            ->addMediaFromString('civil-id-front')
            ->usingFileName('civil-id-front.txt')
            ->withCustomProperties([
                'document_side' => 'front',
            ])
            ->toMediaCollection('identification-documents');

        $this->assertNotNull($media);

        $this->assertSame(
            $media->id,
            $identification->document('front')?->id
        );

        $this->assertNull(
            $identification->document('back')
        );
    }

    public function test_identification_can_have_front_and_back_documents(): void
    {
        $user = User::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        $identification = UserIdentification::create([
            'user_id' => $user->id,
            'identification_type_id' => $identificationType->id,
            'identification_number' => 'CIV-123456',
        ]);

        $front = $identification
            ->addMediaFromString('civil-id-front')
            ->usingFileName('civil-id-front.txt')
            ->withCustomProperties([
                'document_side' => 'front',
            ])
            ->toMediaCollection('identification-documents');

        $back = $identification
            ->addMediaFromString('civil-id-back')
            ->usingFileName('civil-id-back.txt')
            ->withCustomProperties([
                'document_side' => 'back',
            ])
            ->toMediaCollection('identification-documents');

        $this->assertSame(
            $front->id,
            $identification->document('front')?->id
        );

        $this->assertSame(
            $back->id,
            $identification->document('back')?->id
        );
    }

    public function test_different_identification_types_can_have_their_own_documents(): void
    {
        $user = User::factory()->create();

        $civilId = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        $passport = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        $civilIdentification = UserIdentification::create([
            'user_id' => $user->id,
            'identification_type_id' => $civilId->id,
            'identification_number' => 'CIV-123456',
        ]);

        $passportIdentification = UserIdentification::create([
            'user_id' => $user->id,
            'identification_type_id' => $passport->id,
            'identification_number' => 'P1234567',
        ]);

        $civilDocument = $civilIdentification
            ->addMediaFromString('civil-id-front')
            ->usingFileName('civil-id-front.txt')
            ->withCustomProperties([
                'document_side' => 'front',
            ])
            ->toMediaCollection('identification-documents');

        $passportDocument = $passportIdentification
            ->addMediaFromString('passport')
            ->usingFileName('passport.txt')
            ->withCustomProperties([
                'document_side' => 'document',
            ])
            ->toMediaCollection('identification-documents');

        $this->assertSame(
            $civilDocument->id,
            $civilIdentification->document('front')?->id
        );

        $this->assertSame(
            $passportDocument->id,
            $passportIdentification->document('document')?->id
        );

        $this->assertNull(
            $civilIdentification->document('document')
        );

        $this->assertNull(
            $passportIdentification->document('front')
        );
    }
}