<?php

namespace Modules\Committee\Services;

use Illuminate\Support\Facades\DB;
use Modules\Committee\Enums\DesignationScope;
use Modules\Committee\Models\Designation;

class DesignationService
{
    public function create(
        int $organizationId,
        string $name,
        string $code,
        DesignationScope $scope = DesignationScope::CENTRAL,
        ?string $description = null,
        int $sortOrder = 100,
        bool $isKeyPosition = false,
    ): Designation {
        return DB::transaction(function () use (
            $organizationId,
            $name,
            $code,
            $scope,
            $description,
            $sortOrder,
            $isKeyPosition,
        ) {
            return Designation::create([
                'organization_id' => $organizationId,
                'name' => $name,
                'code' => $code,
                'scope' => $scope,
                'description' => $description,
                'is_active' => true,
                'sort_order' => $sortOrder,
                'is_key_position' => $isKeyPosition,
            ]);
        });
    }

    public function update(
        Designation $designation,
        string $name,
        DesignationScope $scope = DesignationScope::CENTRAL,
        ?string $description = null,
        ?int $sortOrder = null,
        ?bool $isKeyPosition = null,
    ): Designation {
        $attributes = [
            'name' => $name,
            'scope' => $scope,
            'description' => $description,
        ];
        if ($sortOrder !== null) { $attributes['sort_order'] = $sortOrder; }
        if ($isKeyPosition !== null) { $attributes['is_key_position'] = $isKeyPosition; }
        $designation->update($attributes);

        return $designation->refresh();
    }

    public function deactivate(
        Designation $designation,
    ): Designation {
        $designation->update([
            'is_active' => false,
        ]);

        return $designation->refresh();
    }

    public function activate(
        Designation $designation,
    ): Designation {
        $designation->update([
            'is_active' => true,
        ]);

        return $designation->refresh();
    }
}