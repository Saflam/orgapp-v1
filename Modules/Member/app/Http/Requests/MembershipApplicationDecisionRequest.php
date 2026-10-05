<?php

namespace Modules\Member\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MembershipApplicationDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => [
                'required',
                Rule::in(['accept', 'reject']),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:5000',
                'required_if:decision,reject',
            ],
            'allow_reapply' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
