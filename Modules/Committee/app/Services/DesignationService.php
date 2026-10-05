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
    ): Designation {
        return DB::transaction(function () use (
            $organizationId,
            $name,
            $code,
            $scope,
            $description,
        ) {
            return Designation::create([
                'organization_id' => $organizationId,
                'name' => $name,
                'code' => $code,
                'scope' => $scope,
                'description' => $description,
                'is_active' => true,
            ]);
        });
    }

    public function update(
        Designation $designation,
        string $name,
        DesignationScope $scope = DesignationScope::CENTRAL,
        ?string $description = null,
    ): Designation {
        $designation->update([
            'name' => $name,
            'scope' => $scope,
            'description' => $description,
        ]);

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