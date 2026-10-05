<?php

namespace Modules\Committee\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Committee\Enums\DesignationScope;

class CreateDesignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100'],
            'scope' => ['required', 'string', 'in:' . implode(',', array_map(fn ($scope) => $scope->value, DesignationScope::cases()))],
            'description' => ['nullable', 'string'],
        ];
    }
}