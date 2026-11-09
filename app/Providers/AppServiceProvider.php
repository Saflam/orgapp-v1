<?php

namespace App\Providers;

use App\Support\Organization\CurrentOrganization;
use App\Http\Middleware\ResolveOrganization;
use App\Http\Middleware\EnsureOrganizationAccess;
use Livewire\Livewire;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use App\Models\User;
use Modules\Core\Models\Organization;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentOrganization::class, function () {
            return new CurrentOrganization();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Re-run organization resolution and access checks for Livewire update requests.
        // CurrentOrganization is request-local, so the initial page request's context
        // is not automatically available during subsequent Livewire AJAX requests.
        Livewire::addPersistentMiddleware([
            ResolveOrganization::class,
            EnsureOrganizationAccess::class,
        ]);

        Blade::if('isMember', function (Organization|int|null $organization = null, ?User $user = null): bool {
            $user ??= auth()->user();

            return $user instanceof User && $user->isMember($organization);
        });
    }
}
