<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserEmergencyContact;
use App\Models\UserPhoneNumber;
use App\Support\PhoneNumbers\PhoneNumberNormalizer;
use Illuminate\Validation\ValidationException;

class UserContactPersistenceService
{
    public function __construct(private readonly PhoneNumberNormalizer $normalizer)
    {
    }

    /**
     * Persist contact information only after the membership application is confirmed.
     * The caller is responsible for wrapping this operation in its registration transaction.
     *
     * @param array<int, array<string, mixed>> $phoneNumbers
     * @param array<int, array<string, mixed>> $emergencyContacts
     */
    public function persist(User $user, array $phoneNumbers, array $emergencyContacts): void
    {
        foreach ($phoneNumbers as $phoneNumber) {
            $normalized = $this->normalizer->normalize(
                (string) $phoneNumber['phone'],
                (string) $phoneNumber['country_iso2'],
            );

            UserPhoneNumber::query()->create([
                'user_id' => $user->id,
                'type' => (string) $phoneNumber['type'],
                'label' => $phoneNumber['label'] ?? null,
                'phone_e164' => $normalized['phone_e164'],
                'country_iso2' => $normalized['country_iso2'],
                'extension' => $phoneNumber['extension'] ?? $normalized['extension'],
                'is_primary' => (bool) ($phoneNumber['is_primary'] ?? false),
            ]);
        }

        foreach ($emergencyContacts as $contact) {
            $normalized = $this->normalizer->normalize(
                (string) $contact['phone'],
                (string) $contact['country_iso2'],
            );

            UserEmergencyContact::query()->create([
                'user_id' => $user->id,
                'contact_name' => (string) $contact['contact_name'],
                'relationship' => $contact['relationship'] ?? null,
                'phone_e164' => $normalized['phone_e164'],
                'country_iso2' => $normalized['country_iso2'],
                'extension' => $contact['extension'] ?? $normalized['extension'],
                'is_primary' => (bool) ($contact['is_primary'] ?? false),
                'notes' => $contact['notes'] ?? null,
            ]);
        }
    }

    /**
     * Update only the contact records supplied by a profile update. Records omitted
     * from the payload are preserved. Set _delete=true with an owned record ID to delete it.
     *
     * @param array<int, array<string, mixed>>|null $phoneNumbers
     * @param array<int, array<string, mixed>>|null $emergencyContacts
     */
    public function syncForProfile(
        User $user,
        ?array $phoneNumbers,
        ?array $emergencyContacts,
    ): void {
        if ($phoneNumbers !== null) {
            foreach ($phoneNumbers as $phoneNumber) {
                $id = isset($phoneNumber['id']) ? (int) $phoneNumber['id'] : null;

                if (($phoneNumber['_delete'] ?? false) === true || ($phoneNumber['_delete'] ?? null) === '1') {
                    $this->ownedPhoneNumber($user, $id)->delete();
                    continue;
                }

                $normalized = $this->normalizer->normalize(
                    (string) $phoneNumber['phone'],
                    (string) $phoneNumber['country_iso2'],
                );

                $record = $id === null
                    ? new UserPhoneNumber()
                    : $this->ownedPhoneNumber($user, $id);

                if ($record->exists && $record->phone_e164 !== $normalized['phone_e164']) {
                    $record->verified_at = null;
                }

                if ((bool) ($phoneNumber['is_primary'] ?? false)) {
                    $user->phoneNumbers()
                        ->where('type', (string) $phoneNumber['type'])
                        ->update(['is_primary' => false]);
                }

                $record->fill([
                    'type' => (string) $phoneNumber['type'],
                    'label' => $phoneNumber['label'] ?? null,
                    'phone_e164' => $normalized['phone_e164'],
                    'country_iso2' => $normalized['country_iso2'],
                    'extension' => $phoneNumber['extension'] ?? $normalized['extension'],
                    'is_primary' => (bool) ($phoneNumber['is_primary'] ?? false),
                ]);

                $user->phoneNumbers()->save($record);
            }
        }

        if ($emergencyContacts !== null) {
            foreach ($emergencyContacts as $contact) {
                $id = isset($contact['id']) ? (int) $contact['id'] : null;

                if (($contact['_delete'] ?? false) === true || ($contact['_delete'] ?? null) === '1') {
                    $this->ownedEmergencyContact($user, $id)->delete();
                    continue;
                }

                $normalized = $this->normalizer->normalize(
                    (string) $contact['phone'],
                    (string) $contact['country_iso2'],
                );

                $record = $id === null
                    ? new UserEmergencyContact()
                    : $this->ownedEmergencyContact($user, $id);

                if ($record->exists && $record->phone_e164 !== $normalized['phone_e164']) {
                    $record->verified_at = null;
                }

                if ((bool) ($contact['is_primary'] ?? false)) {
                    $user->emergencyContacts()->update(['is_primary' => false]);
                }

                $record->fill([
                    'contact_name' => (string) $contact['contact_name'],
                    'relationship' => $contact['relationship'] ?? null,
                    'phone_e164' => $normalized['phone_e164'],
                    'country_iso2' => $normalized['country_iso2'],
                    'extension' => $contact['extension'] ?? $normalized['extension'],
                    'is_primary' => (bool) ($contact['is_primary'] ?? false),
                    'notes' => $contact['notes'] ?? null,
                ]);

                $user->emergencyContacts()->save($record);
            }
        }
    }

    private function ownedPhoneNumber(User $user, ?int $id): UserPhoneNumber
    {
        $record = $id === null ? null : $user->phoneNumbers()->whereKey($id)->first();

        if (! $record) {
            throw ValidationException::withMessages([
                'phone_numbers' => 'The selected phone number does not belong to this user or no longer exists.',
            ]);
        }

        return $record;
    }

    private function ownedEmergencyContact(User $user, ?int $id): UserEmergencyContact
    {
        $record = $id === null ? null : $user->emergencyContacts()->whereKey($id)->first();

        if (! $record) {
            throw ValidationException::withMessages([
                'emergency_contacts' => 'The selected emergency contact does not belong to this user or no longer exists.',
            ]);
        }

        return $record;
    }
}
