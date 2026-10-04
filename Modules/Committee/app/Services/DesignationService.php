<?php

namespace Modules\Committee\Services;

use Illuminate\Support\Facades\DB;
use Modules\Committee\Models\Designation;

class DesignationService
{
    public function create(
        int $organizationId,
        string $name,
        string $code,
        ?string $description = null,
    ): Designation {
        return DB::transaction(function () use (
            $organizationId,
            $name,
            $code,
            $description,
        ) {
            return Designation::create([
                'organization_id' => $organizationId,
                'name' => $name,
                'code' => $code,
                'description' => $description,
                'is_active' => true,
            ]);
        });
    }

    public function update(
        Designation $designation,
        string $name,
        ?string $description = null,
    ): Designation {
        $designation->update([
            'name' => $name,
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