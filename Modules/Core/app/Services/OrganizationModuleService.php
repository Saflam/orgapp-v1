<?php

namespace Modules\Core\Services;

use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Core\Models\OrganizationModule;

class OrganizationModuleService
{
    public function __construct(
        private ModuleRegistry $moduleRegistry,
        private ModuleDependencyRegistry $dependencyRegistry,
    ) {
    }

    public function isEnabled(
        Organization $organization,
        string $module,
    ): bool {
        if (! $this->moduleRegistry->isAvailable($module)) {
            return false;
        }

        return OrganizationModule::query()
            ->where('organization_id', $organization->id)
            ->where('module', $module)
            ->where('is_enabled', true)
            ->exists();
    }

    public function enable(
        Organization $organization,
        string $module,
    ): OrganizationModule {
        $this->ensureModuleIsAvailable($module);
        $this->ensureDependenciesAreEnabled(
            $organization,
            $module,
        );

        return OrganizationModule::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'module' => $module,
            ],
            [
                'is_enabled' => true,
            ],
        );
    }

    public function disable(
        Organization $organization,
        string $module,
    ): OrganizationModule {
        $this->ensureModuleIsAvailable($module);
        $this->ensureModuleCanBeDisabled(
            $organization,
            $module,
        );

        return OrganizationModule::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'module' => $module,
            ],
            [
                'is_enabled' => false,
            ],
        );
    }

    public function getStatus(
        Organization $organization,
        string $module,
    ): bool {
        return $this->isEnabled($organization, $module);
    }

    /**
     * @return array<int, array{
     *     module: string,
     *     is_enabled: bool
     * }>
     */
    public function statuses(
        Organization $organization,
    ): array {
        return collect($this->moduleRegistry->enabledNames())
            ->map(function (string $module) use ($organization): array {
                return [
                    'module' => $module,
                    'is_enabled' => $this->isEnabled(
                        $organization,
                        $module,
                    ),
                ];
            })
            ->values()
            ->all();
    }

    private function ensureModuleIsAvailable(string $module): void
    {
        if (! $this->moduleRegistry->isAvailable($module)) {
            throw ValidationException::withMessages([
                'module' => "The module [{$module}] is not available.",
            ]);
        }
    }

    private function ensureDependenciesAreEnabled(
        Organization $organization,
        string $module,
    ): void {
        foreach (
            $this->dependencyRegistry->dependencies($module)
            as $dependency
        ) {
            if (
                ! $this->moduleRegistry->isAvailable($dependency)
            ) {
                throw ValidationException::withMessages([
                    'module' => "The module [{$module}] requires the module [{$dependency}], which is not available.",
                ]);
            }

            if (
                ! $this->isEnabled(
                    $organization,
                    $dependency,
                )
            ) {
                throw ValidationException::withMessages([
                    'module' => "The module [{$module}] requires the module [{$dependency}] to be enabled for this organization.",
                ]);
            }
        }
    }

    private function ensureModuleCanBeDisabled(
        Organization $organization,
        string $module,
    ): void {
        foreach (
            $this->moduleRegistry->enabledNames()
            as $dependentModule
        ) {
            if ($dependentModule === $module) {
                continue;
            }

            if (
                ! $this->isEnabled(
                    $organization,
                    $dependentModule,
                )
            ) {
                continue;
            }

            if (
                in_array(
                    $module,
                    $this->dependencyRegistry->dependencies(
                        $dependentModule
                    ),
                    true,
                )
            ) {
                throw ValidationException::withMessages([
                    'module' => "The module [{$module}] cannot be disabled because the module [{$dependentModule}] depends on it.",
                ]);
            }
        }
    }
}