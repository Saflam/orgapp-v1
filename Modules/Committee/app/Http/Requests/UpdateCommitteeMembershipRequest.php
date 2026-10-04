<?php

namespace Modules\Committee\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommitteeMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'designation_id' => [
                'required',
                'integer',
                'exists:designations,id',
            ],
        ];
    }
}