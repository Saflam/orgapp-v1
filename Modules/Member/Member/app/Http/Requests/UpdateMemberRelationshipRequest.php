<?php

namespace Modules\Member\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Member\Enums\MemberRelationshipType;

class UpdateMemberRelationshipRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'related_member_id' => [
                'required',
                'integer',
                'exists:members,id',
            ],

            'relationship_type' => [
                'required',
                Rule::enum(MemberRelationshipType::class),
            ],

            'started_at' => [
                'nullable',
                'date',
            ],

            'ended_at' => [
                'nullable',
                'date',
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