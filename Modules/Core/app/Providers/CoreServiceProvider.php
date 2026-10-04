<?php

namespace Modules\Core\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Core\Providers\CoreRoleProvider;
use Modules\Core\Providers\EventServiceProvider;
use Modules\Core\Providers\PermissionServiceProvider;
use Modules\Core\Providers\RoleServiceProvider;
use Modules\Core\Providers\RouteServiceProvider;
use Modules\Core\Services\ModuleDependencyRegistry;
use Modules\Core\Services\RoleRegistry;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CoreServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Core';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'core';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
        PermissionServiceProvider::class,
        RoleServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(
            ModuleDependencyRegistry::class,
            function (): ModuleDependencyRegistry {
                return new ModuleDependencyRegistry();
            },
        );

        $this->app->afterResolving(
            RoleRegistry::class,
            function (RoleRegistry $registry): void {
                $registry->registerProvider(
                    new CoreRoleProvider()
                );
            }
        );
    }

    /**
     * Define module schedules.
     *
     * @param Schedule $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}