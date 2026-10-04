<?php

namespace Modules\Committee\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangeCommitteeMembershipDesignationRequest extends FormRequest
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
            'effective_date' => [
                'required',
                'date',
            ],
        ];
    }
}