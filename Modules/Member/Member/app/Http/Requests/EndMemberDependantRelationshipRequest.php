<?php

namespace Modules\Member\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EndMemberDependantRelationshipRequest extends FormRequest
{
    public function rules(): array
    {
        return [
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