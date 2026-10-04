<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;

class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'organization_id' => Organization::factory(),
            'parent_id' => null,
            'name' => $name,
            'code' => strtoupper(fake()->unique()->bothify('UNIT-####')),
            'is_active' => true,
            'metadata' => [],
        ];
    }
}