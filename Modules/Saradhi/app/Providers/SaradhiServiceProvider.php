<?php

namespace Modules\Saradhi\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Core\Services\ModuleDependencyRegistry;
use Modules\Member\Services\MembershipApplicationExtensionRegistry;
use Modules\Saradhi\Services\SaradhiMembershipApplicationExtension;
use Nwidart\Modules\Support\ModuleServiceProvider;

class SaradhiServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Saradhi';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'saradhi';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->afterResolving(
            ModuleDependencyRegistry::class,
            function (ModuleDependencyRegistry $registry): void {
                $registry->register(
                    'Saradhi',
                    ['Member'],
                );
            }
        );

        $this->app->afterResolving(
            MembershipApplicationExtensionRegistry::class,
            function (
                MembershipApplicationExtensionRegistry $registry
            ): void {
                $registry->register(
                    new SaradhiMembershipApplicationExtension()
                );
            }
        );
    }

    /**
     * @param Schedule $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}