<?php

namespace Modules\Member\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberDependantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'first_name' => [
                'required',
                'string',
                'max:255',
            ],

            'middle_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'last_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'date_of_birth' => [
                'required',
                'date',
                'before_or_equal:today',
            ],

            'gender' => [
                'required',
                'string',
                'max:50',
            ],

            'metadata' => [
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