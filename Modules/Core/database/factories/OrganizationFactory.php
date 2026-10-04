<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Organization;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name' => 'Test Organization',
            'code' => 'TEST-' . fake()->unique()->numberBetween(1000, 9999),
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'is_active' => true,
            'settings' => [],
        ];
    }
}