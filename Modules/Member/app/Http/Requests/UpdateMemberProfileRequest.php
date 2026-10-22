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
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'phone_numbers' => ['sometimes', 'array', 'max:10'],
            'emergency_contacts' => ['sometimes', 'array', 'max:5'],
        ];

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
