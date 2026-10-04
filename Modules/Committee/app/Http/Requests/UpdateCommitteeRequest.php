<?php

namespace Modules\Committee\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommitteeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'unit_id' => ['nullable', 'integer'],
            'parent_committee_id' => ['nullable', 'integer'],
        ];
    }
}