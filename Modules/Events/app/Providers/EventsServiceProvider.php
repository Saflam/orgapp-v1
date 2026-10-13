<?php

namespace Modules\Events\Providers;

use Modules\Core\Services\ModuleDependencyRegistry;
use Modules\Core\Services\PermissionRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Nwidart\Modules\Support\ModuleServiceProvider;

class EventsServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Events';
    protected string $nameLower = 'events';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        RateLimiter::for('events-attendance', function (Request $request): Limit {
            $identity = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(60)->by('events-attendance:'.$request->getHost().':'.$identity);
        });

        RateLimiter::for('events-pass-retry', function (Request $request): Limit {
            $identity = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(10)->by('events-pass-retry:'.$request->getHost().':'.$identity);
        });

        // Public endpoints use tenant-aware keys so one organization's traffic
        // does not consume another organization's quota when sharing a client IP.
        RateLimiter::for('events-public-registration', function (Request $request): Limit {
            return Limit::perMinute(10)->by('events-public-registration:'.$request->getHost().':'.$request->ip());
        });

        RateLimiter::for('events-public-pass-validation', function (Request $request): Limit {
            return Limit::perMinute(30)->by('events-public-pass-validation:'.$request->getHost().':'.$request->ip());
        });
    }

    public function register(): void
    {
        parent::register();

        $this->app->afterResolving(
            PermissionRegistry::class,
            function (PermissionRegistry $registry): void {
                $registry->registerProvider(new EventsPermissionProvider());
            }
        );

        $this->app->afterResolving(
            ModuleDependencyRegistry::class,
            function (ModuleDependencyRegistry $registry): void {
                $registry->register('Events', ['Core', 'Member']);
            }
        );
    }
}
