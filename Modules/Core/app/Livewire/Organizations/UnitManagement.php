<?php

namespace Modules\Core\Livewire\Organizations;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Core\Services\AuthorizationService;

class UnitManagement extends Component
{
    public Organization $organization;

    public ?int $editingUnitId = null;
    public string $name = '';
    public ?int $parent_id = null;
    public bool $is_active = true;
    public string $successMessage = '';

    public function mount(CurrentOrganization $currentOrganization, ?Organization $organization = null): void
    {
        if (auth()->user()?->is_system_admin) {
            abort_unless($organization !== null, 404);
            $this->organization = $organization;
            return;
        }

        $this->organization = $currentOrganization->get();
        $this->ensureCanManage(app(AuthorizationService::class));
    }

    public function save(AuthorizationService $authorization): void
    {
        $this->ensureCanManage($authorization);
        $this->successMessage = '';

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('organization_id', $this->organization->id)),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null && $this->editingUnitId !== null && (int) $value === $this->editingUnitId) {
                        $fail('A unit cannot be its own parent.');
                    }
                },
            ],
            'is_active' => ['required', 'boolean'],
        ]);

        $unit = $this->editingUnitId === null
            ? new Unit()
            : Unit::query()->where('organization_id', $this->organization->id)->findOrFail($this->editingUnitId);

        if ($this->editingUnitId !== null && ! empty($validated['parent_id'])) {
            $ancestor = Unit::query()
                ->where('organization_id', $this->organization->id)
                ->find($validated['parent_id']);

            while ($ancestor !== null) {
                if ($ancestor->id === $this->editingUnitId) {
                    $this->addError('parent_id', 'A unit cannot be moved beneath one of its descendants.');
                    return;
                }

                $ancestor = $ancestor->parent_id
                    ? Unit::query()->where('organization_id', $this->organization->id)->find($ancestor->parent_id)
                    : null;
            }
        }

        $unit->fill([
            'organization_id' => $this->organization->id,
            'name' => trim($validated['name']),
            'parent_id' => $validated['parent_id'] ?? null,
            'is_active' => (bool) $validated['is_active'],
        ])->save();

        $this->resetForm();
        $this->successMessage = 'Unit saved successfully.';
    }

    public function edit(int $unitId, AuthorizationService $authorization): void
    {
        $this->ensureCanManage($authorization);
        $unit = Unit::query()->where('organization_id', $this->organization->id)->findOrFail($unitId);

        $this->editingUnitId = $unit->id;
        $this->name = $unit->name;
        $this->parent_id = $unit->parent_id;
        $this->is_active = $unit->is_active;
        $this->resetValidation();
        $this->successMessage = '';
    }

    public function toggleActive(int $unitId, AuthorizationService $authorization): void
    {
        $this->ensureCanManage($authorization);
        $unit = Unit::query()->where('organization_id', $this->organization->id)->findOrFail($unitId);
        $unit->update(['is_active' => ! $unit->is_active]);
        $this->successMessage = $unit->is_active ? 'Unit activated.' : 'Unit deactivated.';
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    public function render(AuthorizationService $authorization): View
    {
        $this->ensureCanManage($authorization);

        $units = Unit::query()
            ->where('organization_id', $this->organization->id)
            ->with('parent:id,name')
            ->withCount('children')
            ->orderBy('name')
            ->get();

        $parentOptions = $units->reject(fn (Unit $unit) => $unit->id === $this->editingUnitId)
            ->map(fn (Unit $unit) => ['id' => $unit->id, 'name' => $unit->name, 'parent_id' => $unit->parent_id])
            ->values();

        return view('core::livewire.organizations.unit-management', [
            'units' => $units,
            'parentOptions' => $parentOptions,
        ])->layout('core::layouts.organization-admin', [
            'pageTitle' => 'Unit management',
            'layoutOrganization' => $this->organization,
        ]);
    }

    private function ensureCanManage(AuthorizationService $authorization): void
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);

        if ($user->is_system_admin) {
            return;
        }

        abort_unless($authorization->can(
            $user,
            'organization.settings.manage',
            new AuthorizationContext($this->organization->id),
        ), 403);
    }

    private function resetForm(): void
    {
        $this->editingUnitId = null;
        $this->name = '';
        $this->parent_id = null;
        $this->is_active = true;
        $this->resetValidation();
    }
}
