<?php

namespace Modules\Core\Livewire\AccessControl;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Role;
use Modules\Core\Models\RoleAssignment;
use Modules\Core\Services\AuthorizationService;

class OrganizationRoleAssignments extends Component
{
    public Organization $organization;
    public string $search = '';
    public ?int $membershipId = null;
    public string $selectedMembershipLabel = '';
    public array $selectedRoles = [];
    public string $successMessage = '';

    public function mount(CurrentOrganization $currentOrganization, AuthorizationService $authorization): void
    {
        $this->organization = $currentOrganization->get();
        $this->authorizeAccess($authorization, 'organization.roles.assign');
    }

    public function updatedMembershipId(AuthorizationService $authorization): void
    {
        $this->authorizeAccess($authorization, 'organization.roles.assign');
        $this->selectedRoles = $this->membershipId ? RoleAssignment::query()
            ->where('organization_membership_id', $this->membershipId)->where('context_type', 'organization')->where('context_id', $this->organization->id)
            ->whereHas('role', fn ($q) => $q->where(function ($sub) { $sub->whereNull('organization_id')->orWhere('organization_id', $this->organization->id); }))
            ->pluck('role_id')->map(fn ($id) => (string) $id)->all() : [];
    }

    public function updatedSearch(): void
    {
        if ($this->membershipId !== null && trim($this->search) !== $this->selectedMembershipLabel) {
            $this->membershipId = null;
            $this->selectedMembershipLabel = '';
            $this->selectedRoles = [];
        }
    }

    public function selectMembership(int $membershipId, AuthorizationService $authorization): void
    {
        $this->authorizeAccess($authorization, 'organization.roles.assign');
        $membership = $this->organization->organizationMemberships()
            ->with('user:id,name,email')
            ->where('status', 'active')
            ->whereKey($membershipId)
            ->firstOrFail();

        $this->membershipId = $membership->id;
        $this->selectedMembershipLabel = trim(($membership->user?->name ?? 'Unknown user').' — '.($membership->user?->email ?? ''));
        $this->search = '';
        $this->selectedRoles = RoleAssignment::query()
            ->where('organization_membership_id', $membership->id)
            ->where('context_type', 'organization')
            ->where('context_id', $this->organization->id)
            ->whereHas('role', fn ($q) => $q->where(function ($sub) {
                $sub->whereNull('organization_id')->orWhere('organization_id', $this->organization->id);
            }))
            ->pluck('role_id')->map(fn ($id) => (string) $id)->all();
    }

    public function changeMembership(): void
    {
        $this->membershipId = null;
        $this->selectedMembershipLabel = '';
        $this->selectedRoles = [];
        $this->search = '';
    }

    public function save(AuthorizationService $authorization): void
    {
        $this->authorizeAccess($authorization, 'organization.roles.assign');
        $validated = $this->validate([
            'membershipId' => ['required', 'integer', Rule::exists('organization_memberships', 'id')->where(fn ($q) => $q->where('organization_id', $this->organization->id))],
            'selectedRoles' => ['array'],
            'selectedRoles.*' => ['integer', Rule::exists('roles', 'id')->where(fn ($q) => $q->where(function ($sub) { $sub->whereNull('organization_id')->orWhere('organization_id', $this->organization->id); }))],
        ]);
        $membership = $this->organization->organizationMemberships()->whereKey($validated['membershipId'])->firstOrFail();
        abort_unless($membership->status === 'active', 422, 'Roles can only be assigned to active organization memberships.');
        $roleIds = array_map('intval', $validated['selectedRoles'] ?? []);
        $existing = RoleAssignment::query()->where('organization_membership_id', $membership->id)->where('context_type', 'organization')->where('context_id', $this->organization->id)->get();
        foreach ($existing as $assignment) {
            if (! in_array($assignment->role_id, $roleIds, true)) $assignment->delete();
        }
        foreach ($roleIds as $roleId) {
            $role = Role::query()->whereKey($roleId)->where(function ($q) { $q->whereNull('organization_id')->orWhere('organization_id', $this->organization->id); })->firstOrFail();
            RoleAssignment::firstOrCreate(['organization_membership_id' => $membership->id, 'role_id' => $role->id, 'context_type' => 'organization', 'context_id' => $this->organization->id]);
        }
        $this->successMessage = 'User role assignments saved.';
    }

    public function render(AuthorizationService $authorization): View
    {
        $this->authorizeAccess($authorization, 'organization.roles.assign');
        $memberships = $this->membershipId || mb_strlen(trim($this->search)) < 2
            ? collect()
            : $this->organization->organizationMemberships()->with('user:id,name,email')
                ->where('status', 'active')->whereHas('user', fn ($q) => $q->where(function ($sub) {
                    $term = trim($this->search);
                    $sub->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%");
                }))->orderBy('id')->limit(8)->get();
        $roles = Role::query()->where(function ($q) { $q->whereNull('organization_id')->orWhere('organization_id', $this->organization->id); })
            ->where('is_system', false)
            ->orderBy('name')->get();
        return view('core::livewire.access-control.assignments', compact('memberships', 'roles'))
            ->layout('core::layouts.organization-admin', ['pageTitle' => 'User role assignments', 'layoutOrganization' => $this->organization]);
    }

    private function authorizeAccess(AuthorizationService $authorization, string $permission): void
    {
        abort_unless($authorization->can(auth()->user(), $permission, new AuthorizationContext((int) $this->organization->id)), 403);
    }
}
