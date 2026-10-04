<?php

namespace Modules\Core\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization.name' => [
                'required',
                'string',
                'max:255',
            ],

            'organization.code' => [
                'required',
                'string',
                'max:50',
            ],

            'organization.timezone' => [
                'sometimes',
                'string',
                'max:64',
            ],

            'organization.currency' => [
                'sometimes',
                'string',
                'size:3',
            ],

            'super_admin.name' => [
                'required',
                'string',
                'max:255',
            ],

            'super_admin.email' => [
                'required',
                'email',
                'max:255',
            ],

            'super_admin.phone' => [
                'nullable',
                'string',
                'max:30',
            ],
        ];
    }
}