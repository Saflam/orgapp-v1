<?php

namespace Modules\Committee\Livewire;

use App\Support\Organization\CurrentOrganization;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Committee\Enums\CommitteeMembershipStatus;
use Modules\Committee\Models\CommitteeMembership;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationService;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Member\Models\Member;

class CommitteeMemberProfile extends Component
{
    public int $organizationId;
    public int $memberId;

    public function mount(int $member, CurrentOrganization $currentOrganization): void
    {
        $organization = $currentOrganization->get();
        $this->organizationId = (int) $organization->id;
        $this->memberId = $member;
        $this->authorizeView();
        $this->member();
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

    private function member(): Member
    {
        return Member::query()->where('organization_id', $this->organization()->id)
            ->with(['user', 'unit'])->whereKey($this->memberId)->firstOrFail();
    }

    public function render(): View
    {
        $this->authorizeView();
        $member = $this->member();
        $assignments = CommitteeMembership::query()
            ->where('member_id', $member->id)
            ->whereHas('committeeTerm.committee', fn ($query) => $query->where('organization_id', $this->organizationId))
            ->with(['designation', 'committeeTerm.committee.unit'])
            ->orderByDesc('start_date')->orderByDesc('id')->get();
        $currentAssignments = $assignments->filter(fn ($assignment) => $assignment->status === CommitteeMembershipStatus::ACTIVE
            && ($assignment->start_date === null || $assignment->start_date->lte(today()))
            && ($assignment->end_date === null || $assignment->end_date->gte(today()))
            && $assignment->committeeTerm?->status?->value === 'active'
            && $assignment->committeeTerm?->start_date?->lte(today())
            && $assignment->committeeTerm?->end_date?->gte(today())
            && $assignment->committeeTerm?->committee?->status?->value === 'active');
        $historyAssignments = $assignments->reject(fn ($assignment) => $currentAssignments->contains('id', $assignment->id));
        return view('committee::livewire.committee-member-profile', compact('member', 'currentAssignments', 'historyAssignments'))
            ->layout('core::layouts.organization-admin', ['pageTitle' => 'Committee Member Profile']);
    }
}
