<?php

namespace Modules\Committee\Livewire;

use App\Support\Organization\CurrentOrganization;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Committee\Models\Committee;
use Modules\Committee\Models\Designation;
use Modules\Committee\Models\CommitteeMembership;
use Modules\Core\Models\Organization;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Services\AuthorizationService;
use Modules\Core\Services\OrganizationModuleService;

class CommitteeDashboard extends Component
{
    public int $organizationId;
    public function mount(
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        OrganizationModuleService $modules,
    ): void {
        $organization = $currentOrganization->get();
        $this->organizationId = (int) $organization->id;
        abort_unless($modules->isEnabled($organization, 'Committee'), 404);
        $context = new AuthorizationContext((int) $organization->id);
        $user = auth()->user();
        abort_unless($user && (
            $authorization->can($user, 'committee.view', $context)
            || $authorization->can($user, 'committee.manage', $context)
            || $authorization->can($user, 'committee.designations.view', $context)
            || $authorization->can($user, 'committee.designations.manage', $context)
            || $authorization->can($user, 'committee.memberships.view', $context)
            || $authorization->can($user, 'committee.memberships.manage', $context)
        ), 403);
    }

    public function render(CurrentOrganization $currentOrganization): View
    {
        $organization = Organization::query()->findOrFail($this->organizationId);
        abort_unless(app(OrganizationModuleService::class)->isEnabled($organization, 'Committee'), 404);
        $context = new AuthorizationContext((int) $organization->id);
        $user = auth()->user();
        abort_unless($user && (
            app(AuthorizationService::class)->can($user, 'committee.view', $context)
            || app(AuthorizationService::class)->can($user, 'committee.manage', $context)
            || app(AuthorizationService::class)->can($user, 'committee.designations.view', $context)
            || app(AuthorizationService::class)->can($user, 'committee.designations.manage', $context)
            || app(AuthorizationService::class)->can($user, 'committee.memberships.view', $context)
            || app(AuthorizationService::class)->can($user, 'committee.memberships.manage', $context)
        ), 403);
        return view('committee::livewire.dashboard', [
            'committeeCount' => Committee::query()->where('organization_id', $organization->id)->count(),
            'designationCount' => Designation::query()->where('organization_id', $organization->id)->count(),
            'membershipCount' => CommitteeMembership::query()
                ->whereHas('committeeTerm.committee', fn ($query) => $query->where('organization_id', $organization->id))
                ->count(),
        ])->layout('core::layouts.organization-admin', ['pageTitle' => 'Committee']);
    }
}
