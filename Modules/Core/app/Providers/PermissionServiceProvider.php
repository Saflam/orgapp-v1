<?php

namespace Modules\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Services\PermissionRegistry;

class PermissionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            PermissionRegistry::class,
            fn () => new PermissionRegistry()
        );
    }
}