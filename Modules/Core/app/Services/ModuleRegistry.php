<?php

namespace Modules\Core\Services;

use Nwidart\Modules\Facades\Module;

class ModuleRegistry
{
    public function isAvailable(string $module): bool
    {
        return Module::find($module)?->isEnabled() ?? false;
    }

    public function enabledNames(): array
    {
        return collect(Module::allEnabled())
            ->map(fn ($module) => $module->getName())
            ->values()
            ->all();
    }
}