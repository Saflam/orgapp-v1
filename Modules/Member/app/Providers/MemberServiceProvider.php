<?php

namespace Modules\Member\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Member\Console\Commands\SendScheduledMemberNotifications;
use Modules\Member\Console\Commands\GenerateMemberPaymentObligations;
use Modules\Core\Services\PermissionRegistry;
use Modules\Core\Services\RoleRegistry;
use Modules\Member\Providers\RouteServiceProvider;

class MemberServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Member';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'member';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        SendScheduledMemberNotifications::class,
        GenerateMemberPaymentObligations::class,
    ];

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
            PermissionRegistry::class,
            function (PermissionRegistry $registry): void {
                $registry->registerProvider(
                    new MemberPermissionProvider()
                );
            }
        );

        $this->app->afterResolving(
            RoleRegistry::class,
            function (RoleRegistry $registry): void {
                $registry->registerProvider(
                    new MemberRoleProvider()
                );
            }
        );

        $this->app->singleton(
            \Modules\Member\Services\MembershipApplicationExtensionRegistry::class,
            function ($app) {
                return new \Modules\Member\Services\MembershipApplicationExtensionRegistry(
                    $app->make(\Modules\Core\Services\OrganizationModuleService::class),
                );
            },
        );
    }


    /**
     * Define module schedules.
     * 
     * @param $schedule
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command('member:generate-payment-obligations')->dailyAt('07:45');
        $schedule->command('member:send-scheduled-notifications')->dailyAt('08:00');
    }
}
