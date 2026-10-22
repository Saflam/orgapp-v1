<?php

namespace Modules\Finance\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Modules\Finance\Listeners\HandleMembershipPaymentReceived;
use Modules\Member\Events\MembershipPaymentReceived;
use Modules\Core\Services\ModuleDependencyRegistry;
use Modules\Core\Services\PermissionRegistry;
use Modules\Core\Services\RoleRegistry;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Modules\Finance\Providers\FinancePermissionProvider;
use Modules\Finance\Providers\FinanceRoleProvider;

class FinanceServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Finance';
    protected string $nameLower = 'finance';
    
    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->afterResolving(PermissionRegistry::class, function (PermissionRegistry $registry): void {
            $registry->registerProvider(new FinancePermissionProvider());
        });

        $this->app->afterResolving(RoleRegistry::class, function (RoleRegistry $registry): void {
            $registry->registerProvider(new FinanceRoleProvider());
        });

        $this->app->afterResolving(ModuleDependencyRegistry::class, function (ModuleDependencyRegistry $registry): void {
            $registry->register('Finance', ['Core']);
        });

        $this->app->singleton(\Modules\Finance\Services\FinanceScopeService::class);
        $this->app->singleton(\Modules\Finance\Services\FinanceAccountProvisioningService::class);
        $this->app->singleton(\Modules\Finance\Services\FinancePeriodService::class);
        $this->app->singleton(\Modules\Finance\Services\FinanceJournalService::class);
        $this->app->singleton(\Modules\Finance\Services\FinanceReceiptService::class);
        $this->app->singleton(\Modules\Finance\Services\MemberPaymentFinanceService::class);

        Event::listen(
            MembershipPaymentReceived::class,
            HandleMembershipPaymentReceived::class,
        );
        $this->app->singleton(\Modules\Finance\Services\FinanceItemService::class);
        $this->app->singleton(\Modules\Finance\Services\FinanceCustomerService::class);
    }
}
