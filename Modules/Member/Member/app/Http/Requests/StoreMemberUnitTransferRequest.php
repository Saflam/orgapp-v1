<?php

namespace Modules\Member\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberUnitTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'min:1'],
            'to_unit_id' => ['required', 'integer', 'min:1'],
            'spouse_included' => ['sometimes', 'boolean'],
        ];
    }
}
