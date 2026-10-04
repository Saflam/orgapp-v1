<?php

namespace Modules\Core\Tests\Feature;

use Modules\Core\Services\ModuleRegistry;
use Tests\TestCase;

class ModuleRegistryTest extends TestCase
{
    public function test_it_returns_names_of_enabled_modules(): void
    {
        $registry = app(ModuleRegistry::class);

        $names = $registry->enabledNames();

        $this->assertIsArray($names);
        $this->assertContains('Member', $names);
    }
}