<?php

namespace Modules\Member\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_id' => ['sometimes', 'nullable', 'integer', 'exists:units,id'],
            'membership_number' => ['sometimes', 'required', 'string', 'max:255'],
            'joined_at' => ['sometimes', 'nullable', 'date'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:50'],
            'blood_group' => ['sometimes', 'required', 'string', 'max:20'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'max:50'],
            'whatsapp_calling_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'home_contact' => ['sometimes', 'nullable', 'string', 'max:50'],
            'profession' => ['sometimes', 'nullable', 'string', 'max:255'],
            'company' => ['sometimes', 'nullable', 'string', 'max:255'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'address' => ['sometimes', 'array'],
            'address.address_type' => ['sometimes', 'string', 'max:50'],
            'address.address_line_1' => ['sometimes', 'required', 'string', 'max:255'],
            'address.address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.locality' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.state' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.postal_code' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address.country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
