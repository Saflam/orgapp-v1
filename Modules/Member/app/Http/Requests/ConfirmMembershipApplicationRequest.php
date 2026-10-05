<?php

namespace Modules\Member\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmMembershipApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'membership_number' => [
                'required',
                'string',
                'max:255',
            ],
            'starts_at' => [
                'required',
                'date',
            ],
        ];
    }
}
