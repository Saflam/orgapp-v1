<?php

namespace Modules\Events\Http\Requests\Api;

class UpdateEventRequest extends StoreEventRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['title'][0] = 'sometimes';
        $rules['starts_at'] = ['sometimes', 'date'];
        $rules['ends_at'] = ['sometimes', 'date'];
        $rules['scope'][0] = 'sometimes';
        $rules['visibility'][0] = 'sometimes';
        $rules['registration_fields'][0] = 'sometimes';
        foreach (['registration_opens_at', 'registration_closes_at', 'registration_capacity', 'venue_capacity'] as $field) {
            $rules[$field][0] = 'sometimes';
        }
        unset($rules['unit_id'][3]);
        $rules['unit_id'] = ['nullable', 'integer', 'min:1'];
        return $rules;
    }
}
