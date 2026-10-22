<?php

namespace Modules\Committee\Livewire;

use App\Support\Organization\CurrentOrganization;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Committee\Models\Designation;
use Modules\Committee\Enums\DesignationScope;
use Modules\Committee\Services\DesignationService;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\OrganizationModule;
use Modules\Committee\Services\DesignationPermissionService;
use Modules\Core\Services\AuthorizationService;
use Modules\Core\Services\OrganizationModuleService;

class DesignationIndex extends Component
{
    use WithPagination;

    public int $organizationId;

    public string $search = '';
    public string $scope = 'all';

    public bool $showCreateForm = false;
    public ?int $editingDesignationId = null;
    public array $selectedPermissionIds = [];
    public bool $canManagePermissions = false;
    public string $name = '';
    public string $designationScope = 'central';
    public string $description = '';
    public string $sortOrder = '100';
    public string $permissionSearch = '';
    public bool $isKeyPosition = false;

    public function mount(CurrentOrganization $currentOrganization, AuthorizationService $authorization, OrganizationModuleService $modules): void
    {
        $organization = $currentOrganization->get();
        $this->organizationId = (int) $organization->id;
        abort_unless($modules->isEnabled($organization, 'Committee'), 404);
        $context = new AuthorizationContext((int) $organization->id);
        abort_unless(auth()->user() && (
            $authorization->can(auth()->user(), 'committee.designations.view', $context)
            || $authorization->can(auth()->user(), 'committee.designations.manage', $context)
        ), 403);
    }

    public function updatingSearch(): void { $this->resetPage(); }

    public function create(): void
    {
        $this->authorizeManage();
        $this->resetValidation();
        $this->resetForm();
        $this->showCreateForm = true;
    }

    public function edit(int $designationId): void
    {
        $this->authorizeManage();
        $designation = $this->findDesignation($designationId);
        $this->resetValidation();
        $this->editingDesignationId = $designation->id;
        $this->name = $designation->name;
        $this->designationScope = $designation->scope->value;
        $this->description = $designation->description ?? '';
        $this->sortOrder = (string) $designation->sort_order;
        $this->isKeyPosition = $designation->is_key_position;
        $this->selectedPermissionIds = $this->canManageDesignationPermissions()
            ? $designation->permissions()->pluck('permissions.id')->map(fn ($id) => (int) $id)->all()
            : [];
        $this->showCreateForm = true;
    }

    public function cancelCreate(): void
    {
        $this->showCreateForm = false;
        $this->resetForm();
        $this->resetValidation();
    }

    public function save(
        DesignationService $service,
        DesignationPermissionService $permissionService,
    ): void {
        $organization = $this->organization();
        $this->authorizeManage();
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'designationScope' => ['required', Rule::in(array_map(fn ($case) => $case->value, DesignationScope::cases()))],
            'description' => ['nullable', 'string', 'max:5000'],
            'sortOrder' => ['required', 'integer', 'min:1', 'max:9999'],
            'isKeyPosition' => ['boolean'],
        ]);

        $permissionIds = collect($this->selectedPermissionIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        if ($this->canManageDesignationPermissions()) {
            $allowedPermissionIds = $this->availablePermissions($organization)
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($permissionIds->diff($allowedPermissionIds)->isNotEmpty()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'selectedPermissionIds' => 'One or more selected permissions are not available to this organization.',
                ]);
            }
        }

        if ($this->editingDesignationId !== null) {
            $designation = $this->findDesignation($this->editingDesignationId);
            $service->update(
                $designation,
                trim($validated['name']),
                DesignationScope::from($validated['designationScope']),
                $validated['description'] ?: null,
                (int) $validated['sortOrder'],
                (bool) ($validated['isKeyPosition'] ?? false),
            );
            $message = 'Designation updated successfully.';
        } else {
            $designation = $service->create(
                (int) $organization->id,
                trim($validated['name']),
                '',
                DesignationScope::from($validated['designationScope']),
                $validated['description'] ?: null,
                (int) $validated['sortOrder'],
                (bool) ($validated['isKeyPosition'] ?? false),
            );
            $message = 'Designation created successfully.';
        }

        if ($this->canManageDesignationPermissions()) {
            $permissionService->sync($designation, $permissionIds->all());
        }

        $this->showCreateForm = false;
        $this->resetForm();
        session()->flash('committee_message', $message);
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->reset(['name', 'designationScope', 'description', 'sortOrder', 'isKeyPosition', 'editingDesignationId', 'selectedPermissionIds']);
        $this->designationScope = 'central';
        $this->sortOrder = '100';
        $this->isKeyPosition = false;
        $this->selectedPermissionIds = [];
    }

    private function findDesignation(int $designationId): Designation
    {
        return Designation::query()
            ->where('organization_id', $this->organizationId)
            ->findOrFail($designationId);
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
        $context = new AuthorizationContext((int) $organization->id);
        abort_unless(auth()->user() && app(AuthorizationService::class)->can(auth()->user(), 'committee.designations.manage', $context), 403);
    }

    private function organization(): Organization
    {
        $organization = Organization::query()->findOrFail($this->organizationId);
        abort_unless(app(OrganizationModuleService::class)->isEnabled($organization, 'Committee'), 404);
        return $organization;
    }
    public function updatingScope(): void { $this->resetPage(); }

    public function render(CurrentOrganization $currentOrganization): View
    {
        $organization = $this->organization();
        $context = new AuthorizationContext((int) $organization->id);
        abort_unless(auth()->user() && (
            app(AuthorizationService::class)->can(auth()->user(), 'committee.designations.view', $context)
            || app(AuthorizationService::class)->can(auth()->user(), 'committee.designations.manage', $context)
        ), 403);
        $canManage = app(AuthorizationService::class)->can(auth()->user(), 'committee.designations.manage', new AuthorizationContext((int) $organization->id));
        $this->canManagePermissions = $this->canManageDesignationPermissions();
        $permissionsByModule = $this->canManagePermissions
            ? $this->availablePermissions($organization)
                ->when(trim($this->permissionSearch) !== '', function ($query): void {
                    $term = '%' . trim($this->permissionSearch) . '%';
                    $query->where(function ($nested) use ($term): void {
                        $nested->where('name', 'like', $term)
                            ->orWhere('code', 'like', $term)
                            ->orWhere('description', 'like', $term)
                            ->orWhere('module', 'like', $term);
                    });
                })
                ->get()
                ->groupBy('module')
            : collect();
        $designations = Designation::query()
            ->where('organization_id', $organization->id)
            ->withCount('permissions')
            ->when(trim($this->search) !== '', function ($query): void {
                $term = '%' . trim($this->search) . '%';
                $query->where(function ($nested) use ($term): void {
                    $nested->where('name', 'like', $term)->orWhere('code', 'like', $term);
                });
            })
            ->when($this->scope !== 'all', fn ($query) => $query->where('scope', $this->scope))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15);

        return view('committee::livewire.designations', compact('designations', 'canManage', 'permissionsByModule'))
            ->layout('core::layouts.organization-admin', ['pageTitle' => 'Designations']);
    }
}
