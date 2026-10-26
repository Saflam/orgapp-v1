<?php

namespace Modules\Member\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberDependantRelationshipRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'dependant_id' => [
                'required',
                'integer',
                'exists:member_dependants,id',
            ],
            'relationship_type' => [
                'required',
                'string',
                'in:spouse,child',
            ],
            'started_at' => [
                'required',
                'date',
            ],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}