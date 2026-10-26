<?php

namespace Modules\Member\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\IdentificationType;
use Modules\Member\Models\UserIdentification;
use Modules\Member\Services\UserIdentificationService;
use Tests\TestCase;

class UserIdentificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_a_user_identification(): void
    {
        $user = User::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        $service = app(UserIdentificationService::class);

        $identification = $service->create(
            user: $user,
            identificationType: $identificationType,
            data: [
                'identification_number' => 'CIV-123456',
                'metadata' => [
                    'source' => 'application',
                ],
            ],
        );

        $this->assertInstanceOf(
            UserIdentification::class,
            $identification
        );

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
            ['source' => 'application'],
            $identification->metadata
        );
    }

    public function test_it_can_find_a_user_identification(): void
    {
        $user = User::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        $created = UserIdentification::create([
            'user_id' => $user->id,
            'identification_type_id' => $identificationType->id,
            'identification_number' => 'P1234567',
        ]);

        $service = app(UserIdentificationService::class);

        $found = $service->find(
            user: $user,
            identificationType: $identificationType,
        );

        $this->assertNotNull($found);

        $this->assertSame(
            $created->id,
            $found->id
        );
    }

    public function test_find_returns_null_when_identification_does_not_exist(): void
    {
        $user = User::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        $service = app(UserIdentificationService::class);

        $found = $service->find(
            user: $user,
            identificationType: $identificationType,
        );

        $this->assertNull($found);
    }

    public function test_it_can_save_a_new_identification(): void
    {
        $user = User::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        $service = app(UserIdentificationService::class);

        $identification = $service->save(
            user: $user,
            identificationType: $identificationType,
            data: [
                'identification_number' => 'CIV-123456',
            ],
        );

        $this->assertDatabaseHas(
            'user_identifications',
            [
                'id' => $identification->id,
                'identification_number' => 'CIV-123456',
            ]
        );
    }

    public function test_save_updates_existing_identification_instead_of_creating_duplicate(): void
    {
        $user = User::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        $service = app(UserIdentificationService::class);

        $first = $service->save(
            user: $user,
            identificationType: $identificationType,
            data: [
                'identification_number' => 'CIV-123456',
            ],
        );

        $second = $service->save(
            user: $user,
            identificationType: $identificationType,
            data: [
                'identification_number' => 'CIV-999999',
            ],
        );

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertDatabaseCount(
            'user_identifications',
            1
        );

        $this->assertDatabaseHas(
            'user_identifications',
            [
                'id' => $first->id,
                'identification_number' => 'CIV-999999',
            ]
        );
    }

    public function test_it_can_update_an_existing_identification(): void
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
                'source' => 'application',
            ],
        ]);

        $service = app(UserIdentificationService::class);

        $updated = $service->update(
            identification: $identification,
            data: [
                'identification_number' => 'P7654321',
            ],
        );

        $this->assertSame(
            $identification->id,
            $updated->id
        );

        $this->assertSame(
            'P7654321',
            $updated->identification_number
        );

        $this->assertSame(
            ['source' => 'application'],
            $updated->metadata
        );
    }

    public function test_it_can_update_metadata(): void
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
                'source' => 'application',
            ],
        ]);

        $service = app(UserIdentificationService::class);

        $updated = $service->update(
            identification: $identification,
            data: [
                'identification_number' => 'P7654321',
                'metadata' => [
                    'source' => 'profile_update',
                    'verified' => true,
                ],
            ],
        );

        $this->assertSame(
            [
                'source' => 'profile_update',
                'verified' => true,
            ],
            $updated->metadata
        );
    }

    public function test_it_returns_all_identifications_for_a_user(): void
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

        $service = app(UserIdentificationService::class);

        $identifications = $service->all($user);

        $this->assertCount(
            2,
            $identifications
        );

        $this->assertTrue(
            $identifications->every(
                fn (UserIdentification $identification) =>
                    $identification->identificationType !== null
            )
        );
    }

    public function test_identifications_from_another_user_are_not_returned(): void
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

        $service = app(UserIdentificationService::class);

        $identifications = $service->all($userA);

        $this->assertCount(
            1,
            $identifications
        );

        $this->assertSame(
            'CIV-111111',
            $identifications->first()->identification_number
        );
    }

    public function test_it_can_delete_an_identification(): void
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

        $service = app(UserIdentificationService::class);

        $service->delete($identification);

        $this->assertDatabaseMissing(
            'user_identifications',
            [
                'id' => $identification->id,
            ]
        );
    }
}