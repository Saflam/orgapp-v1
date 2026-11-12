<?php

namespace Modules\Committee\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Core\Services\AuthorizationContributorRegistry;
use Modules\Core\Services\PermissionRegistry;
use Modules\Member\Services\MemberProfileSectionExtensionRegistry;
use Modules\Committee\Services\TrusteeMemberProfileSection;

class CommitteeServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Committee';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'committee';

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
    ];

    public function register(): void
    {
        parent::register();

        $this->app->afterResolving(
            PermissionRegistry::class,
            function (PermissionRegistry $registry): void {
                $registry->registerProvider(new CommitteePermissionProvider());
            }
        );

        $this->app->afterResolving(
            AuthorizationContributorRegistry::class,
            function (AuthorizationContributorRegistry $registry): void {
                $registry->register(
                    new \Modules\Committee\Services\CommitteeAuthorizationContributor()
                );
            }
        );

        $this->app->afterResolving(
            MemberProfileSectionExtensionRegistry::class,
            function (MemberProfileSectionExtensionRegistry $registry): void {
                $registry->register($this->app->make(TrusteeMemberProfileSection::class));
            }
        );
    }

    /**
     * Define module schedules.
     * 
     * @param $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
