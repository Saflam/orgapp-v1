<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Organization;
use Modules\Core\Models\OrganizationSetting;

class OrganizationSettingsService
{
    public function get(
        Organization $organization,
        string $key,
        mixed $default = null,
    ): mixed {
        $setting = $organization->settings()
            ->where('key', $key)
            ->first();

        return $setting?->value ?? $default;
    }

    public function set(
        Organization $organization,
        string $key,
        mixed $value,
    ): OrganizationSetting {
        return DB::transaction(function () use (
            $organization,
            $key,
            $value,
        ) {
            return OrganizationSetting::updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'key' => $key,
                ],
                [
                    'value' => $value,
                ],
            );
        });
    }

    public function forget(
        Organization $organization,
        string $key,
    ): void {
        OrganizationSetting::query()
            ->where('organization_id', $organization->id)
            ->where('key', $key)
            ->delete();
    }
}