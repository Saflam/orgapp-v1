<?php

namespace Modules\Member\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

class MemberProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,

            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'phone' => $this->user?->phone,

            'organization_id' => $this->organization_id,
            'unit_id' => $this->unit_id,
            'membership_number' => $this->membership_number,

            'date_of_birth' => $this->user?->details?->date_of_birth?->toDateString(),

            'phone_numbers' => $this->user?->phoneNumbers?->map(fn ($phoneNumber): array => [
                'id' => $phoneNumber->id,
                'type' => $phoneNumber->type,
                'label' => $phoneNumber->label,
                'phone_e164' => $phoneNumber->phone_e164,
                'country_iso2' => $phoneNumber->country_iso2,
                ...$this->phoneDisplayData($phoneNumber->phone_e164),
                'extension' => $phoneNumber->extension,
                'is_primary' => (bool) $phoneNumber->is_primary,
                'verified_at' => $phoneNumber->verified_at?->toISOString(),
            ])->values()->all() ?? [],

            'emergency_contacts' => $this->user?->emergencyContacts?->map(fn ($contact): array => [
                'id' => $contact->id,
                'contact_name' => $contact->contact_name,
                'relationship' => $contact->relationship,
                'phone_e164' => $contact->phone_e164,
                'country_iso2' => $contact->country_iso2,
                ...$this->phoneDisplayData($contact->phone_e164),
                'extension' => $contact->extension,
                'is_primary' => (bool) $contact->is_primary,
                'notes' => $contact->notes,
                'verified_at' => $contact->verified_at?->toISOString(),
            ])->values()->all() ?? [],

            'joined_at' => $this->joined_at?->toDateString(),
            'metadata' => $this->metadata,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /** @return array{country_calling_code: ?string, national_number: ?string} */
    private function phoneDisplayData(string $phoneE164): array
    {
        try {
            $number = PhoneNumberUtil::getInstance()->parse($phoneE164, null);

            return [
                'country_calling_code' => (string) $number->getCountryCode(),
                'national_number' => (string) $number->getNationalNumber(),
            ];
        } catch (NumberParseException) {
            // Legacy or manually corrected records should not break profile reads.
            return [
                'country_calling_code' => null,
                'national_number' => null,
            ];
        }
    }
}
