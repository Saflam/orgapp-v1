<?php

namespace Modules\Member\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'unit_id' => [
                'nullable',
                'integer',
                'exists:units,id',
            ],
            'membership_number' => [
                'required',
                'string',
                'max:255',
            ],
            'date_of_birth' => [
                'nullable',
                'date',
            ],
            'joined_at' => [
                'nullable',
                'date',
            ],
            'metadata' => [
                'nullable',
                'array',
            ],
        ];
    }
}