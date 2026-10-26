<?php

namespace Modules\Member\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectMemberUnitTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'side' => ['required', 'string', 'in:outgoing,incoming'],
            'reason' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }
}
