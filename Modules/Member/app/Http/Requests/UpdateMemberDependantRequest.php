<?php

namespace Modules\Member\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMemberDependantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'first_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'middle_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'last_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'date_of_birth' => [
                'sometimes',
                'required',
                'date',
                'before_or_equal:today',
            ],

            'gender' => [
                'sometimes',
                'required',
                'string',
                'max:50',
            ],

            'metadata' => [
                'sometimes',
                'nullable',
                'array',
            ],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}