<?php
namespace Modules\Core\Services;

use App\Models\User;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Unit;

class OrganizationManagementAccessService
{
    public function __construct(
        private AuthorizationService $authorization,
        private OrganizationModuleService $modules,
    ) {}

    public function canManageAnyModule(User $user, Organization $organization): bool
    {
        return $user->is_system_admin || $this->manageablePermissions($user, $organization)->isNotEmpty();
    }

    public function canManagePermission(User $user, Organization $organization, string $code): bool
    {
        $context = new AuthorizationContext((int) $organization->id);
        if ($this->authorization->can($user, $code, $context)) return true;

        return Unit::query()->where('organization_id', $organization->id)->get(['id'])
            ->contains(fn (Unit $unit) => $this->authorization->can(
                $user, $code, new AuthorizationContext((int) $organization->id, (int) $unit->id)
            ));
    }

    public function manageablePermissions(User $user, Organization $organization)
    {
        $context = new AuthorizationContext((int) $organization->id);
        return Permission::query()->where('code', 'like', '%.manage')->get()
            ->filter(function (Permission $permission) use ($user, $organization, $context) {
                if ($permission->module && ! in_array($permission->module, ['Core', 'Member'], true)
                    && ! $this->modules->isEnabled($organization, $permission->module)) return false;

                return $this->authorization->can($user, $permission->code, $context)
                    || Unit::query()->where('organization_id', $organization->id)->get(['id'])
                        ->contains(fn (Unit $unit) => $this->authorization->can(
                            $user, $permission->code,
                            new AuthorizationContext((int) $organization->id, (int) $unit->id)
                        ));
            })->values();
    }

    public function manageableModules(User $user, Organization $organization): array
    {
        if ($user->is_system_admin) return []; 
        $routes = [
            'Member' => 'member.directory',
            'Committee' => 'committee.dashboard',
            'Finance' => 'finance.accounts',
            'Core' => 'core.organization.settings',
        ];
        return $this->manageablePermissions($user, $organization)
            ->groupBy(fn (Permission $permission) => $permission->module ?: 'Organization')
            ->map(function ($permissions, $module) use ($routes) {
                $route = $routes[$module] ?? null;
                return $route && \Illuminate\Support\Facades\Route::has($route)
                    ? ['name' => $module, 'route' => $route]
                    : null;
            })->filter()->values()->all();
    }
}
