<?php

namespace Modules\Committee\Livewire;

use App\Support\Organization\CurrentOrganization;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Committee\Enums\CommitteeMembershipStatus;
use Modules\Committee\Models\CommitteeTerm;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationService;
use Modules\Core\Services\OrganizationModuleService;

class CommitteeMembershipHistory extends Component
{
    public int $organizationId;
    public int $termId;

    public function mount(int $term, CurrentOrganization $currentOrganization): void
    {
        $organization = $currentOrganization->get();
        $this->organizationId = (int) $organization->id;
        $this->termId = $term;
        $this->authorizeView();
        $this->term();
    }

    private function organization(): Organization
    {
        $organization = Organization::query()->findOrFail($this->organizationId);
        abort_unless(app(OrganizationModuleService::class)->isEnabled($organization, 'Committee'), 404);
        return $organization;
    }

    private function authorizeView(): void
    {
        $user = auth()->user();
        $context = new AuthorizationContext($this->organizationId);
        $authorization = app(AuthorizationService::class);
        abort_unless($user && (
            $authorization->can($user, 'committee.view', $context)
            || $authorization->can($user, 'committee.manage', $context)
            || $authorization->can($user, 'committee.memberships.view', $context)
            || $authorization->can($user, 'committee.memberships.manage', $context)
        ), 403);
    }

    private function term(): CommitteeTerm
    {
        return CommitteeTerm::query()
            ->whereHas('committee', fn ($query) => $query->where('organization_id', $this->organization()->id))
            ->with(['committee.unit', 'memberships' => fn ($query) => $query
                ->whereIn('status', [CommitteeMembershipStatus::ENDED->value, CommitteeMembershipStatus::CANCELLED->value])
                ->with(['member.user', 'member.unit', 'designation'])
                ->orderByDesc('end_date')->orderByDesc('id')])
            ->whereKey($this->termId)->firstOrFail();
    }

    public function render(): View
    {
        $this->authorizeView();
        $term = $this->term();
        return view('committee::livewire.committee-membership-history', compact('term'))
            ->layout('core::layouts.organization-admin', ['pageTitle' => 'Committee Membership History']);
    }
}
