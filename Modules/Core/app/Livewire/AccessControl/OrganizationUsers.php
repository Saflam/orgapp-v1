<?php

namespace Modules\Core\Livewire\AccessControl;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Models\RoleAssignment;
use Modules\Core\Services\AuthorizationService;

class OrganizationUsers extends Component
{
    use WithPagination;

    public Organization $organization;
    public string $search = '';
    public string $status = 'all';

    public function mount(CurrentOrganization $currentOrganization, AuthorizationService $authorization): void
    {
        $this->organization = $currentOrganization->get();
        $this->authorizeAccess($authorization, 'organization.users.view');
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatus(): void { $this->resetPage(); }

    public function render(AuthorizationService $authorization): View
    {
        $this->authorizeAccess($authorization, 'organization.users.view');
        $memberships = $this->organization->organizationMemberships()
            ->with(['user:id,name,email,phone'])
            ->when($this->status !== 'all', fn (Builder $q) => $q->where('status', $this->status))
            ->whereHas('user', fn (Builder $q) => $q->where(function (Builder $search) {
                $term = trim($this->search);
                $search->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            }))
            ->latest('id')->paginate(15);

        foreach ($memberships as $membership) {
            $membership->setRelation('organizationRoles', RoleAssignment::query()
                ->where('organization_membership_id', $membership->id)
                ->active()->where('context_type', 'organization')
                ->where('context_id', $this->organization->id)
                ->with('role:id,name,code')->get()->pluck('role')->filter());
        }

        return view('core::livewire.access-control.users', compact('memberships'))
            ->layout('core::layouts.organization-admin', ['pageTitle' => 'Organization users', 'layoutOrganization' => $this->organization]);
    }

    private function authorizeAccess(AuthorizationService $authorization, string $permission): void
    {
        abort_unless($authorization->can(auth()->user(), $permission, new AuthorizationContext((int) $this->organization->id)), 403);
    }
}
