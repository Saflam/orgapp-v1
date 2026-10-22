<?php

namespace Modules\Committee\Livewire;

use App\Support\Organization\CurrentOrganization;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Modules\Committee\Enums\DesignationScope;
use Modules\Committee\Models\Designation;
use Modules\Committee\Services\DesignationPermissionService;
use Modules\Committee\Services\DesignationService;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Models\OrganizationModule;
use Modules\Core\Models\Permission;
use Modules\Core\Services\AuthorizationService;
use Modules\Core\Services\OrganizationModuleService;

class DesignationEdit extends Component
{
    public int $organizationId;
    public int $designationId;

    public string $name = '';
    public string $designationScope = 'central';
    public string $description = '';
    public string $sortOrder = '100';
    public bool $isKeyPosition = false;
    public array $selectedPermissionIds = [];
    public string $permissionSearch = '';
    public bool $canManagePermissions = false;

    public function mount(
        int $designation,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        OrganizationModuleService $modules,
    ): void {
        $organization = $currentOrganization->get();
        $this->organizationId = (int) $organization->id;
        $this->designationId = $designation;
        abort_unless($modules->isEnabled($organization, 'Committee'), 404);

        $context = new AuthorizationContext((int) $organization->id);
        abort_unless(auth()->user() && $authorization->can(auth()->user(), 'committee.designations.manage', $context), 403);

        $record = $this->designation();
        $this->name = $record->name;
        $this->designationScope = $record->scope->value;
        $this->description = $record->description ?? '';
        $this->sortOrder = (string) $record->sort_order;
        $this->isKeyPosition = $record->is_key_position;
        $this->canManagePermissions = $this->canManageDesignationPermissions();
        if ($this->canManagePermissions) {
            $this->selectedPermissionIds = $record->permissions()->pluck('permissions.id')->map(fn ($id) => (int) $id)->all();
        }
    }

    public function save(
        DesignationService $service,
        DesignationPermissionService $permissionService,
    ): mixed {
        $organization = $this->organization();
        $this->authorizeManage();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'designationScope' => ['required', Rule::in(array_map(fn ($case) => $case->value, DesignationScope::cases()))],
            'description' => ['nullable', 'string', 'max:5000'],
            'sortOrder' => ['required', 'integer', 'min:1', 'max:9999'],
            'isKeyPosition' => ['boolean'],
            'selectedPermissionIds' => ['array'],
            'selectedPermissionIds.*' => ['integer', 'distinct'],
        ]);

        $permissionIds = collect($this->selectedPermissionIds)->map(fn ($id) => (int) $id)->unique()->values();
        if ($this->canManageDesignationPermissions()) {
            $allowedPermissionIds = $this->availablePermissions($organization)->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($permissionIds->diff($allowedPermissionIds)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'selectedPermissionIds' => 'One or more selected permissions are not available to this organization.',
                ]);
            }
        }

        $designation = $this->designation();
        $service->update(
            $designation,
            trim($validated['name']),
            DesignationScope::from($validated['designationScope']),
            $validated['description'] ?: null,
            (int) $validated['sortOrder'],
            (bool) ($validated['isKeyPosition'] ?? false),
        );

        if ($this->canManageDesignationPermissions()) {
            $permissionService->sync($designation, $permissionIds->all());
        }

        session()->flash('committee_message', 'Designation updated successfully.');
        return $this->redirectRoute('committee.designations', navigate: true);
    }

    private function designation(): Designation
    {
        return Designation::query()
            ->where('organization_id', $this->organizationId)
            ->findOrFail($this->designationId);
    }

    private function canManageDesignationPermissions(): bool
    {
        $organization = $this->organization();
        return auth()->user() && app(AuthorizationService::class)->can(
            auth()->user(),
            'committee.designation_permissions.manage',
            new AuthorizationContext((int) $organization->id),
        );
    }

    private function availablePermissions(Organization $organization)
    {
        $enabledModules = OrganizationModule::query()
            ->where('organization_id', $organization->id)
            ->where('is_enabled', true)
            ->pluck('module')
            ->push('Core', 'Member')
            ->unique()
            ->values();

        return Permission::query()
            ->whereIn('module', $enabledModules)
            ->orderBy('module')
            ->orderBy('name');
    }

    private function authorizeManage(): void
    {
        $organization = $this->organization();
        abort_unless(auth()->user() && app(AuthorizationService::class)->can(
            auth()->user(),
            'committee.designations.manage',
            new AuthorizationContext((int) $organization->id),
        ), 403);
    }

    private function organization(): Organization
    {
        $organization = Organization::query()->findOrFail($this->organizationId);
        abort_unless(app(OrganizationModuleService::class)->isEnabled($organization, 'Committee'), 404);
        return $organization;
    }

    public function render(): View
    {
        $organization = $this->organization();
        $this->canManagePermissions = $this->canManageDesignationPermissions();
        $permissionsByModule = collect();

        if ($this->canManagePermissions) {
            $permissionsByModule = $this->availablePermissions($organization)
                ->when(trim($this->permissionSearch) !== '', function ($query) {
                    $term = '%' . trim($this->permissionSearch) . '%';
                    $query->where(function ($nested) use ($term): void {
                        $nested->where('name', 'like', $term)
                            ->orWhere('code', 'like', $term)
                            ->orWhere('description', 'like', $term)
                            ->orWhere('module', 'like', $term);
                    });
                })
                ->get()
                ->groupBy('module');
        }

        return view('committee::livewire.designation-edit', compact('permissionsByModule'))
            ->layout('core::layouts.organization-admin', ['pageTitle' => 'Edit designation']);
    }
}
