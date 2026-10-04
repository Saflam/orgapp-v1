<?php

namespace Tests\Feature\Users;

use App\Models\User;
use App\Models\UserDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_detail_can_store_structured_name(): void
    {
        $user = User::factory()->create();

        $detail = UserDetail::create([
            'user_id' => $user->id,
            'first_name' => 'Muhammed',
            'middle_name' => 'Safeer',
            'last_name' => 'Ali',
        ]);

        $this->assertDatabaseHas('user_details', [
            'user_id' => $user->id,
            'first_name' => 'Muhammed',
            'middle_name' => 'Safeer',
            'last_name' => 'Ali',
        ]);

        $this->assertSame('Muhammed', $detail->first_name);
        $this->assertSame('Safeer', $detail->middle_name);
        $this->assertSame('Ali', $detail->last_name);
    }

    public function test_middle_name_can_be_null(): void
    {
        $user = User::factory()->create();

        $detail = UserDetail::create([
            'user_id' => $user->id,
            'first_name' => 'Muhammed',
            'last_name' => 'Ali',
        ]);

        $this->assertNull($detail->middle_name);
    }

    public function test_last_name_can_be_null(): void
    {
        $user = User::factory()->create();

        $detail = UserDetail::create([
            'user_id' => $user->id,
            'first_name' => 'Madonna',
        ]);

        $this->assertNull($detail->last_name);
    }

    public function test_all_structured_name_fields_can_be_null_for_legacy_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Abdul Rahman',
        ]);

        $detail = UserDetail::create([
            'user_id' => $user->id,
        ]);

        $this->assertNull($detail->first_name);
        $this->assertNull($detail->middle_name);
        $this->assertNull($detail->last_name);

        $this->assertSame('Abdul Rahman', $user->name);
    }

    public function test_user_can_access_user_detail(): void
    {
        $user = User::factory()->create();

        UserDetail::create([
            'user_id' => $user->id,
            'first_name' => 'Muhammed',
            'last_name' => 'Ali',
        ]);

        $this->assertSame('Muhammed', $user->details->first_name);
        $this->assertSame('Ali', $user->details->last_name);
    }

    public function test_user_detail_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $detail = UserDetail::create([
            'user_id' => $user->id,
            'first_name' => 'Muhammed',
        ]);

        $this->assertTrue($detail->user->is($user));
    }
}