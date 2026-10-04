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
            'unit_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:units,id',
            ],
            'membership_number' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'date_of_birth' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'joined_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'metadata' => [
                'sometimes',
                'nullable',
                'array',
            ],
        ];
    }
}