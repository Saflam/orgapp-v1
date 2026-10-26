<?php

namespace Modules\Member\Http\Requests;

use App\Rules\ValidPhoneNumberForCountry;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMemberProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'phone_country_iso2' => ['sometimes', 'nullable', 'string', 'size:2', 'alpha'],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'blood_group' => ['sometimes', 'nullable', 'string', 'max:10'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'max:50'],
            'whatsapp_country_iso2' => ['sometimes', 'nullable', 'string', 'size:2', 'alpha'],
            'whatsapp_calling_code' => ['sometimes', 'nullable', 'string', 'max:8'],
            'addresses' => ['sometimes', 'array', 'max:10'],
            'addresses.*' => ['required', 'array'],
            'addresses.*.id' => ['sometimes', 'integer', 'min:1'],
            'addresses.*._delete' => ['sometimes', 'boolean'],
            'addresses.*.address_type' => ['required_unless:addresses.*._delete,1', 'nullable', 'string', 'max:50'],
            'addresses.*.address_line_1' => ['required_unless:addresses.*._delete,1', 'nullable', 'string', 'max:255'],
            'addresses.*.address_line_2' => ['nullable', 'string', 'max:255'],
            'addresses.*.locality' => ['nullable', 'string', 'max:255'],
            'addresses.*.city' => ['nullable', 'string', 'max:100'],
            'addresses.*.state' => ['nullable', 'string', 'max:100'],
            'addresses.*.postal_code' => ['nullable', 'string', 'max:20'],
            'addresses.*.country' => ['nullable', 'string', 'max:100'],
            'addresses.*.is_primary' => ['sometimes', 'boolean'],
            'phone_numbers' => ['sometimes', 'array', 'max:10'],
            'emergency_contacts' => ['sometimes', 'array', 'max:5'],
        ];

        // Country selectors are sent by the web profile form. Keep the API contract
        // backward-compatible for clients that still submit only the legacy phone field.
        if (trim((string) $this->input('phone', '')) !== '' && filled($this->input('phone_country_iso2'))) {
            $rules['phone'][] = new ValidPhoneNumberForCountry(
                (string) $this->input('phone_country_iso2'),
            );
        }
        if (trim((string) $this->input('whatsapp', '')) !== '' && filled($this->input('whatsapp_country_iso2'))) {
            $rules['whatsapp'][] = new ValidPhoneNumberForCountry(
                (string) $this->input('whatsapp_country_iso2'),
            );
        }

        foreach ((array) $this->input('phone_numbers', []) as $index => $phoneNumber) {
            $phoneNumber = is_array($phoneNumber) ? $phoneNumber : [];
            $prefix = "phone_numbers.{$index}";
            $rules[$prefix] = ['required', 'array'];
            $rules["{$prefix}.id"] = ['sometimes', 'integer', 'min:1'];
            $rules["{$prefix}._delete"] = ['sometimes', 'boolean'];

            if (($phoneNumber['_delete'] ?? false) === true || ($phoneNumber['_delete'] ?? null) === '1') {
                $rules["{$prefix}.id"] = ['required', 'integer', 'min:1'];
                continue;
            }

            $rules["{$prefix}.type"] = ['required', 'string', 'max:50'];
            $rules["{$prefix}.phone"] = [
                'required', 'string', 'max:50',
                new ValidPhoneNumberForCountry(
                    is_string($phoneNumber['country_iso2'] ?? null) ? $phoneNumber['country_iso2'] : null,
                ),
            ];
            $rules["{$prefix}.country_iso2"] = ['required', 'string', 'size:2', 'alpha'];
            $rules["{$prefix}.label"] = ['sometimes', 'nullable', 'string', 'max:100'];
            $rules["{$prefix}.extension"] = ['sometimes', 'nullable', 'string', 'max:20'];
            $rules["{$prefix}.is_primary"] = ['sometimes', 'boolean'];
        }

        foreach ((array) $this->input('emergency_contacts', []) as $index => $contact) {
            $contact = is_array($contact) ? $contact : [];
            $prefix = "emergency_contacts.{$index}";
            $rules[$prefix] = ['required', 'array'];
            $rules["{$prefix}.id"] = ['sometimes', 'integer', 'min:1'];
            $rules["{$prefix}._delete"] = ['sometimes', 'boolean'];

            if (($contact['_delete'] ?? false) === true || ($contact['_delete'] ?? null) === '1') {
                $rules["{$prefix}.id"] = ['required', 'integer', 'min:1'];
                continue;
            }

            $rules["{$prefix}.contact_name"] = ['required', 'string', 'max:255'];
            $rules["{$prefix}.relationship"] = ['sometimes', 'nullable', 'string', 'max:100'];
            $rules["{$prefix}.phone"] = [
                'required', 'string', 'max:50',
                new ValidPhoneNumberForCountry(
                    is_string($contact['country_iso2'] ?? null) ? $contact['country_iso2'] : null,
                ),
            ];
            $rules["{$prefix}.country_iso2"] = ['required', 'string', 'size:2', 'alpha'];
            $rules["{$prefix}.extension"] = ['sometimes', 'nullable', 'string', 'max:20'];
            $rules["{$prefix}.is_primary"] = ['sometimes', 'boolean'];
            $rules["{$prefix}.notes"] = ['sometimes', 'nullable', 'string', 'max:2000'];
        }

        return $rules;
    }
}
