<?php

namespace Modules\Member\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMemberDependantRelationshipRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'relationship_type' => [
                'sometimes',
                'required',
                'string',
                'in:spouse,child',
            ],
            'started_at' => [
                'sometimes',
                'required',
                'date',
            ],
            'ended_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}