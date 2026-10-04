<?php

namespace Modules\Core\Services;

class ModuleDependencyRegistry
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $dependencies = [];

    /**
     * Register dependencies for a module.
     *
     * @param array<int, string> $dependencies
     */
    public function register(
        string $module,
        array $dependencies,
    ): void {
        $this->dependencies[$module] = array_values(
            array_unique($dependencies)
        );
    }

    /**
     * @return array<int, string>
     */
    public function dependencies(string $module): array
    {
        return $this->dependencies[$module] ?? [];
    }
}