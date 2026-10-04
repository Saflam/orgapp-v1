<?php

namespace Tests\Feature\Users;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_address_belongs_to_a_user(): void
    {
        $user = User::factory()->create();

        $address = UserAddress::create([
            'user_id' => $user->id,
            'address_type' => 'home',
            'address_line_1' => '123 Main Road',
            'city' => 'Kochi',
            'state' => 'Kerala',
            'postal_code' => '682001',
            'country' => 'India',
            'is_primary' => true,
        ]);

        $this->assertTrue(
            $address->user->is($user)
        );
    }

    public function test_a_user_can_have_multiple_addresses(): void
    {
        $user = User::factory()->create();

        $user->addresses()->create([
            'address_type' => 'home',
            'address_line_1' => 'Home Address',
        ]);

        $user->addresses()->create([
            'address_type' => 'work',
            'address_line_1' => 'Work Address',
        ]);

        $this->assertCount(
            2,
            $user->addresses
        );
    }

    public function test_address_metadata_is_cast_to_array(): void
    {
        $user = User::factory()->create();

        $address = UserAddress::create([
            'user_id' => $user->id,
            'address_type' => 'home',
            'address_line_1' => '123 Main Road',
            'metadata' => [
                'landmark' => 'Near Temple',
            ],
        ]);

        $this->assertSame(
            [
                'landmark' => 'Near Temple',
            ],
            $address->metadata
        );
    }

    public function test_deleting_user_deletes_addresses(): void
    {
        $user = User::factory()->create();

        $user->addresses()->create([
            'address_type' => 'home',
            'address_line_1' => '123 Main Road',
        ]);

        $user->delete();

        $this->assertDatabaseCount(
            'user_addresses',
            0
        );
    }
}