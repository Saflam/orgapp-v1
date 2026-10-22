<?php

namespace Modules\Member\Livewire;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationService;
use Modules\Member\Models\MembershipType;

class MembershipTypeManagement extends Component
{
    public Organization $organization;

    public ?int $editingId = null;
    public string $name = '';
    public string $code = '';
    public string $description = '';
    public bool $isActive = true;
    public string $successMessage = '';

    public function mount(
        ?Organization $organization = null,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
    ): void {
        // Livewire may supply an empty Organization model when this tenant
        // route has no {organization} parameter. Prefer it only when it is persisted.
        $this->organization = ($organization instanceof Organization
            && $organization->exists
            && $organization->id !== null)
                ? $organization
                : $currentOrganization->get();
        $this->ensureCanManage($authorization);
    }

    public function edit(int $id, AuthorizationService $authorization): void
    {
        $this->ensureCanManage($authorization);
        $type = MembershipType::query()
            ->where('organization_id', $this->organization->id)
            ->findOrFail($id);

        $this->editingId = $type->id;
        $this->name = $type->name;
        $this->code = $type->code;
        $this->description = $type->description ?? '';
        $this->isActive = $type->is_active;
        $this->resetValidation();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->code = '';
        $this->description = '';
        $this->isActive = true;
        $this->resetValidation();
    }

    public function save(AuthorizationService $authorization): void
    {
        $this->successMessage = '';
        $this->ensureCanManage($authorization);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('membership_types', 'code')
                    ->where('organization_id', $this->organization->id)
                    ->ignore($this->editingId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'isActive' => ['required', 'boolean'],
        ]);

        MembershipType::query()->updateOrCreate(
            [
                'organization_id' => $this->organization->id,
                'id' => $this->editingId,
            ],
            [
                'name' => trim($validated['name']),
                'code' => strtoupper(trim($validated['code'])),
                'description' => $validated['description'] ?? null,
                'is_active' => (bool) $validated['isActive'],
            ],
        );

        $this->resetForm();
        $this->successMessage = 'Membership type saved successfully.';
    }

    public function toggleActive(int $id, AuthorizationService $authorization): void
    {
        $this->ensureCanManage($authorization);
        $type = MembershipType::query()
            ->where('organization_id', $this->organization->id)
            ->findOrFail($id);
        $type->update(['is_active' => ! $type->is_active]);
        $this->successMessage = 'Membership type status updated.';
    }

    public function render(AuthorizationService $authorization): View
    {
        $this->ensureCanManage($authorization);
        $types = MembershipType::query()
            ->where('organization_id', $this->organization->id)
            ->orderBy('name')
            ->get();

        return view('member::livewire.membership-types.manage', compact('types'))
            ->layout('core::layouts.organization-admin', [
                'pageTitle' => 'Membership types',
                'layoutOrganization' => $this->organization,
            ]);
    }

    private function ensureCanManage(AuthorizationService $authorization): void
    {
        $user = auth()->user();

        abort_unless(
            $user
            && $this->organization->exists
            && $this->organization->id !== null
            && $authorization->can(
                $user,
                'organization.settings.manage',
                new AuthorizationContext((int) $this->organization->id),
            ),
            403,
        );
    }
}
