<?php

namespace Modules\Events\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Models\Organization;
use Modules\Events\Enums\EventStatus;
use Modules\Events\Enums\EventVisibility;
use Modules\Events\Models\Event;

class StoreEventParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route enforces events.invitations.send.
        return true;
    }

    public function rules(): array
    {
        return [
            'participant_type' => ['required', Rule::in(['member', 'guest'])],
            'member_id' => [
                'required_if:participant_type,member',
                'nullable',
                'integer',
                'min:1',
            ],
            'name' => ['required_if:participant_type,guest', 'nullable', 'string', 'max:200'],
            'email' => ['required_if:participant_type,guest', 'nullable', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:40'],
        ];
    }
}
