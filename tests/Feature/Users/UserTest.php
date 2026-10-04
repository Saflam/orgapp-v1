<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_have_a_phone_number(): void
    {
        $user = User::factory()->create([
            'phone' => '+96550000000',
        ]);

        $this->assertSame('+96550000000', $user->phone);
    }

    public function test_phone_number_is_optional(): void
    {
        $user = User::factory()->create([
            'phone' => null,
        ]);

        $this->assertNull($user->phone);
    }

    public function test_phone_number_must_be_unique(): void
    {
        User::factory()->create([
            'phone' => '+96550000000',
        ]);

        $this->expectException(QueryException::class);

        User::factory()->create([
            'phone' => '+96550000000',
        ]);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create([
            'email' => 'member@example.com',
        ]);

        $this->expectException(QueryException::class);

        User::factory()->create([
            'email' => 'member@example.com',
        ]);
    }

    public function test_phone_verified_at_is_cast_to_datetime(): void
    {
        $user = User::factory()->create([
            'phone_verified_at' => '2026-09-30 10:30:00',
        ]);

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $user->phone_verified_at
        );
    }

    public function test_email_verified_at_is_cast_to_datetime(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => '2026-09-30 10:30:00',
        ]);

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $user->email_verified_at
        );
    }

    public function test_password_is_hashed(): void
    {
        $user = User::factory()->create([
            'password' => 'secret-password',
        ]);

        $this->assertNotSame('secret-password', $user->password);
        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check(
                'secret-password',
                $user->password
            )
        );
    }
}