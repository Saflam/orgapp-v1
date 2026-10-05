<?php

namespace Modules\Committee\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Committee\Enums\DesignationScope;

class UpdateDesignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scope' => ['required', 'string', 'in:' . implode(',', array_map(fn ($scope) => $scope->value, DesignationScope::cases()))],
        ];
    }
}