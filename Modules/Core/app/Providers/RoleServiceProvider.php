<?php

namespace Modules\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Core\Services\RoleRegistry;

class RoleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            RoleRegistry::class,
            function ($app): RoleRegistry {
                return new RoleRegistry(
                    $app->make(OrganizationModuleService::class)
                );
            }
        );
    }

    public function boot(): void
    {
        //
    }
}