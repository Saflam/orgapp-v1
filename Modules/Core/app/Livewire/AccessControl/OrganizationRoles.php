<?php

namespace Modules\Core\Livewire\AccessControl;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Services\AuthorizationService;
use Modules\Core\Services\OrganizationModuleService;

class OrganizationRoles extends Component
{
    public Organization $organization;
    public ?int $editingRoleId = null;
    public string $name = '';
    public string $description = '';
    public array $selectedPermissions = [];
    public string $permissionSearch = '';
    public string $successMessage = '';

    public function mount(CurrentOrganization $currentOrganization, AuthorizationService $authorization): void
    {
        $this->organization = $currentOrganization->get();
        $this->authorizeAccess($authorization, 'organization.roles.manage');
    }

    public function edit(int $roleId, AuthorizationService $authorization): void
    {
        $this->authorizeAccess($authorization, 'organization.roles.manage');
        $role = Role::query()->where('organization_id', $this->organization->id)->where('is_system', false)->findOrFail($roleId);
        $this->editingRoleId = $role->id;
        $this->name = $role->name;
        $this->description = $role->description ?? '';
        $this->selectedPermissions = $role->permissions()->pluck('permissions.id')->map(fn ($id) => (string) $id)->all();
        $this->successMessage = '';
        $this->resetValidation();
    }

    public function save(AuthorizationService $authorization, OrganizationModuleService $modules): void
    {
        $this->authorizeAccess($authorization, 'organization.roles.manage');
        $this->successMessage = '';
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);

        $allowedModules = collect(['Core', 'Member']);
        foreach (Permission::query()->select('module')->distinct()->pluck('module') as $module) {
            if ($modules->isEnabled($this->organization, (string) $module)) $allowedModules->push($module);
        }
        $allowedIds = Permission::query()->whereIn('module', $allowedModules->unique()->all())->pluck('id')->map(fn ($id) => (string) $id)->all();
        $invalid = array_diff(array_map('strval', $validated['selectedPermissions'] ?? []), $allowedIds);
        if ($invalid !== []) {
            $this->addError('selectedPermissions', 'Some selected permissions belong to modules that are not enabled for this organization.');
            return;
        }

        $role = $this->editingRoleId === null
            ? new Role()
            : Role::query()->where('organization_id', $this->organization->id)->where('is_system', false)->findOrFail($this->editingRoleId);
        $role->fill(['organization_id' => $this->organization->id, 'name' => trim($validated['name']), 'description' => $validated['description'] ?? null, 'is_system' => false])->save();
        $role->permissions()->sync(array_map('intval', $validated['selectedPermissions'] ?? []));
        $this->resetForm();
        $this->successMessage = 'Role and permissions saved successfully.';
    }

    public function delete(int $roleId, AuthorizationService $authorization): void
    {
        $this->authorizeAccess($authorization, 'organization.roles.manage');
        $role = Role::query()->where('organization_id', $this->organization->id)->where('is_system', false)->findOrFail($roleId);
        if ($role->assignments()->exists()) {
            $this->addError('roleDelete', 'This role is assigned to users. Reassign or revoke those assignments before deleting it.');
            return;
        }
        $role->delete();
        $this->successMessage = 'Role deleted.';
    }

    public function cancelEdit(): void { $this->resetForm(); }

    public function resetForm(): void
    {
        $this->reset(['editingRoleId', 'name', 'description', 'selectedPermissions', 'permissionSearch']);
        $this->resetValidation();
    }

    public function render(AuthorizationService $authorization, OrganizationModuleService $modules): View
    {
        $this->authorizeAccess($authorization, 'organization.roles.manage');
        $enabled = collect(['Core', 'Member']);
        foreach (Permission::query()->select('module')->distinct()->pluck('module') as $module) {
            if ($modules->isEnabled($this->organization, (string) $module)) $enabled->push($module);
        }
        $permissions = Permission::query()->whereIn('module', $enabled->unique()->all())
            ->when(trim($this->permissionSearch) !== '', function ($q) {
                $term = '%'.trim($this->permissionSearch).'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)->orWhere('code', 'like', $term)->orWhere('description', 'like', $term)->orWhere('module', 'like', $term));
            })->orderBy('module')->orderBy('name')->get()->groupBy('module');
        $roles = Role::query()->where('organization_id', $this->organization->id)->where('is_system', false)->withCount('permissions')->withCount('assignments')->orderBy('name')->get();
        $builtInRoles = Role::query()->whereNull('organization_id')->where('is_system', true)->withCount('permissions')->orderBy('name')->get();
        return view('core::livewire.access-control.roles', compact('roles', 'builtInRoles', 'permissions'))
            ->layout('core::layouts.organization-admin', ['pageTitle' => 'Roles and permissions', 'layoutOrganization' => $this->organization]);
    }

    private function authorizeAccess(AuthorizationService $authorization, string $permission): void
    {
        abort_unless($authorization->can(auth()->user(), $permission, new AuthorizationContext((int) $this->organization->id)), 403);
    }
}
