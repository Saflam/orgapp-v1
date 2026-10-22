<?php

namespace Tests\Feature\Users;

use App\Models\User;
use App\Models\UserEmergencyContact;
use App\Models\UserPhoneNumber;
use App\Support\PhoneNumbers\PhoneNumberNormalizer;
use App\Services\UserContactPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class UserPhoneNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_have_multiple_phone_numbers_with_different_types(): void
    {
        $user = User::factory()->create();

        $mobile = UserPhoneNumber::create([
            'user_id' => $user->id,
            'type' => 'mobile',
            'phone_e164' => '+919876543210',
            'country_iso2' => 'IN',
            'is_primary' => true,
        ]);

        $whatsapp = UserPhoneNumber::create([
            'user_id' => $user->id,
            'type' => 'whatsapp',
            'phone_e164' => '+971501234567',
            'country_iso2' => 'AE',
        ]);

        $this->assertCount(2, $user->phoneNumbers);
        $this->assertTrue($mobile->user->is($user));
        $this->assertSame('whatsapp', $user->phoneNumbers->firstWhere('type', 'whatsapp')->type);
        $this->assertSame('+971501234567', $whatsapp->phone_e164);
    }

    public function test_user_can_have_an_emergency_contact(): void
    {
        $user = User::factory()->create();

        $contact = UserEmergencyContact::create([
            'user_id' => $user->id,
            'contact_name' => 'Example Contact',
            'relationship' => 'Sibling',
            'phone_e164' => '+919876543210',
            'country_iso2' => 'IN',
            'is_primary' => true,
        ]);

        $this->assertTrue($contact->user->is($user));
        $this->assertSame($contact->id, $user->emergencyContacts->first()->id);
    }

    public function test_normalizer_returns_e164_and_edit_form_fields_for_national_number(): void
    {
        $normalized = app(PhoneNumberNormalizer::class)->normalize(
            '9876543210',
            'IN',
        );

        $this->assertSame('+919876543210', $normalized['phone_e164']);
        $this->assertSame('IN', $normalized['country_iso2']);
        $this->assertSame('91', $normalized['country_calling_code']);
        $this->assertSame('9876543210', $normalized['national_number']);
        $this->assertNull($normalized['extension']);
    }

    public function test_normalizer_accepts_valid_international_number(): void
    {
        $normalized = app(PhoneNumberNormalizer::class)->normalize('+971501234567');

        $this->assertSame('+971501234567', $normalized['phone_e164']);
        $this->assertSame('AE', $normalized['country_iso2']);
        $this->assertSame('971', $normalized['country_calling_code']);
    }

    public function test_normalizer_rejects_national_number_without_country(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(PhoneNumberNormalizer::class)->normalize('9876543210');
    }

    public function test_normalizer_rejects_number_that_does_not_match_selected_country(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(PhoneNumberNormalizer::class)->normalize('+919876543210', 'AE');
    }

    public function test_contact_persistence_normalizes_additional_numbers_and_emergency_contacts(): void
    {
        $user = User::factory()->create();

        app(UserContactPersistenceService::class)->persist(
            user: $user,
            phoneNumbers: [
                [
                    'type' => 'whatsapp',
                    'phone' => '9876543210',
                    'country_iso2' => 'IN',
                    'label' => 'Personal WhatsApp',
                ],
            ],
            emergencyContacts: [
                [
                    'contact_name' => 'Emergency Person',
                    'relationship' => 'Sibling',
                    'phone' => '+971501234567',
                    'country_iso2' => 'AE',
                ],
            ],
        );

        $this->assertDatabaseHas('user_phone_numbers', [
            'user_id' => $user->id,
            'type' => 'whatsapp',
            'phone_e164' => '+919876543210',
            'country_iso2' => 'IN',
        ]);

        $this->assertDatabaseHas('user_emergency_contacts', [
            'user_id' => $user->id,
            'contact_name' => 'Emergency Person',
            'phone_e164' => '+971501234567',
            'country_iso2' => 'AE',
        ]);
    }

    public function test_contact_persistence_rejects_a_number_for_the_wrong_country(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        app(UserContactPersistenceService::class)->persist(
            user: $user,
            phoneNumbers: [
                [
                    'type' => 'whatsapp',
                    'phone' => '+919876543210',
                    'country_iso2' => 'AE',
                ],
            ],
            emergencyContacts: [],
        );
    }
}
