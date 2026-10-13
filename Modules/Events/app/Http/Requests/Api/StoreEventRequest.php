<?php

namespace Modules\Events\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Events\Enums\EventScope;
use Modules\Events\Enums\EventVisibility;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is enforced by the route permission middleware.
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'venue_name' => ['nullable', 'string', 'max:200'],
            'venue_address' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'timezone' => ['nullable', 'timezone'],
            'scope' => ['required', Rule::enum(EventScope::class)],
            'unit_id' => ['nullable', 'integer', 'min:1', 'required_if:scope,unit'],
            'visibility' => ['required', Rule::enum(EventVisibility::class)],
            'registration_fields' => ['nullable', 'array', 'max:20'],
            'registration_opens_at' => ['nullable', 'date'],
            'registration_closes_at' => ['nullable', 'date', 'after_or_equal:registration_opens_at'],
            'registration_capacity' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'venue_capacity' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'registration_fields.*.key' => ['required_with:registration_fields', 'string', 'regex:/^[a-z][a-z0-9_]{1,49}$/', 'distinct'],
            'registration_fields.*.label' => ['required_with:registration_fields', 'string', 'max:100'],
            'registration_fields.*.type' => ['required_with:registration_fields', Rule::in(['text', 'email', 'phone', 'select', 'checkbox'])],
            'registration_fields.*.required' => ['sometimes', 'boolean'],
            'registration_fields.*.options' => ['required_if:registration_fields.*.type,select', 'array', 'max:50'],
            'registration_fields.*.options.*' => ['string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
