<?php

namespace Modules\Committee\Livewire;

use App\Support\Organization\CurrentOrganization;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Committee\Enums\CommitteeTermStatus;
use Modules\Committee\Enums\DesignationScope;
use Modules\Committee\Models\CommitteeMembership;
use Modules\Committee\Models\CommitteeTerm;
use Modules\Committee\Models\Designation;
use Modules\Committee\Services\CommitteeMembershipService;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationService;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Member\Models\Member;

class CommitteeMemberManagement extends Component
{
    public int $organizationId;
    public int $termId;
    public string $memberSearch = '';
    public string $memberId = '';
    public string $designationId = '';
    public ?int $concludingMembershipId = null;
    public string $conclusionDate = '';
    public string $conclusionReason = '';

    public function mount(
        int $term,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        OrganizationModuleService $modules,
    ): void {
        $organization = $currentOrganization->get();
        $this->organizationId = (int) $organization->id;
        $this->termId = $term;
        $this->conclusionDate = today()->toDateString();
        abort_unless($modules->isEnabled($organization, 'Committee'), 404);
        $this->term();
        $this->authorizeView($authorization);
    }

    public function updatedMemberSearch(): void
    {
        $this->memberId = '';
    }

    public function selectMember(int $memberId): void
    {
        $this->authorizeManage();
        $member = Member::query()->where('organization_id', $this->organization()->id)->with('user')->whereKey($memberId)->firstOrFail();
        $this->memberId = (string) $member->id;
        $this->memberSearch = trim((string) $member->user?->name) . ' · ' . ($member->membership_number ?: ('Member #' . $member->id));
        $this->resetValidation('memberId');
    }

    public function clearSelectedMember(): void
    {
        $this->memberId = '';
        $this->memberSearch = '';
    }

    public function addCommitteeMember(CommitteeMembershipService $service): void
    {
        $this->authorizeManage();
        $term = $this->term();
        abort_unless($term->status === CommitteeTermStatus::ACTIVE, 422, 'Members can only be managed for an active committee term.');
        $validated = $this->validate(['memberId' => ['required', 'integer'], 'designationId' => ['required', 'integer']]);
        $member = Member::query()->where('organization_id', $this->organization()->id)->whereKey((int) $validated['memberId'])->firstOrFail();
        $scope = $term->committee->unit_id === null ? DesignationScope::CENTRAL : DesignationScope::UNIT;
        $designation = Designation::query()->where('organization_id', $this->organization()->id)->where('is_active', true)->where('scope', $scope->value)->whereKey((int) $validated['designationId'])->firstOrFail();
        try {
            $service->addMember($term, $member, $designation);
        } catch (\Throwable $exception) {
            report($exception);
            $this->addError('memberId', $exception instanceof \DomainException ? $exception->getMessage() : 'The member could not be assigned. Check the member and designation.');
            return;
        }
        $this->memberId = '';
        $this->designationId = '';
        $this->memberSearch = '';
        session()->flash('committee_message', 'Member assigned to the committee successfully.');
    }

    public function beginConclusion(int $membershipId): void
    {
        $this->authorizeManage();
        $membership = $this->term()->memberships()->whereKey($membershipId)->firstOrFail();
        abort_unless($membership->status === \Modules\Committee\Enums\CommitteeMembershipStatus::ACTIVE, 422);
        $this->concludingMembershipId = $membership->id;
        $this->conclusionDate = today()->toDateString();
        $this->conclusionReason = '';
        $this->resetValidation(['conclusionDate', 'conclusionReason']);
    }

    public function cancelConclusion(): void
    {
        $this->concludingMembershipId = null;
        $this->conclusionReason = '';
        $this->resetValidation(['conclusionDate', 'conclusionReason']);
    }

    public function concludeCommitteeMember(CommitteeMembershipService $service): void
    {
        $this->authorizeManage();
        $validated = $this->validate([
            'concludingMembershipId' => ['required', 'integer'],
            'conclusionDate' => ['required', 'date'],
            'conclusionReason' => ['required', 'string', 'min:5', 'max:5000'],
        ]);
        $membership = $this->term()->memberships()->whereKey((int) $validated['concludingMembershipId'])->firstOrFail();
        try {
            $service->end($membership, \Illuminate\Support\Carbon::parse($validated['conclusionDate']), $validated['conclusionReason']);
            $this->cancelConclusion();
            session()->flash('committee_message', 'Committee assignment concluded and added to membership history.');
        } catch (\Throwable $exception) {
            report($exception);
            $this->addError('conclusionReason', $exception instanceof \DomainException ? $exception->getMessage() : 'The committee assignment could not be concluded.');
        }
    }

    private function term(): CommitteeTerm
    {
        $organization = $this->organization();
        return CommitteeTerm::query()
            ->whereHas('committee', fn ($query) => $query->where('organization_id', $organization->id))
            ->with(['committee.unit', 'memberships.member.user', 'memberships.member.unit', 'memberships.designation'])
            ->whereKey($this->termId)->firstOrFail();
    }

    private function organization(): Organization
    {
        $organization = Organization::query()->findOrFail($this->organizationId);
        abort_unless(app(OrganizationModuleService::class)->isEnabled($organization, 'Committee'), 404);
        return $organization;
    }

    private function authorizeView(?AuthorizationService $authorization = null): void
    {
        $user = auth()->user();
        $context = new AuthorizationContext($this->organizationId);
        $service = $authorization ?? app(AuthorizationService::class);
        abort_unless($user && ($service->can($user, 'committee.view', $context) || $service->can($user, 'committee.manage', $context) || $service->can($user, 'committee.memberships.view', $context) || $service->can($user, 'committee.memberships.manage', $context)), 403);
    }

    private function authorizeManage(): void
    {
        $user = auth()->user();
        $context = new AuthorizationContext($this->organizationId);
        $authorization = app(AuthorizationService::class);
        abort_unless($user && ($authorization->can($user, 'committee.manage', $context) || $authorization->can($user, 'committee.memberships.manage', $context)), 403);
    }

    public function render(): View
    {
        $organization = $this->organization();
        $this->authorizeView();
        $term = $this->term();
        $term->setRelation('memberships', $term->memberships->filter(fn ($assignment) => $assignment->status === \Modules\Committee\Enums\CommitteeMembershipStatus::ACTIVE)->sortBy(function ($assignment) {
            return sprintf('%05d-%s', $assignment->designation?->sort_order ?? 100, mb_strtolower((string) $assignment->member?->user?->name));
        })->values());
        $formerMembershipCount = CommitteeMembership::query()->where('committee_term_id', $term->id)->whereIn('status', [\Modules\Committee\Enums\CommitteeMembershipStatus::ENDED->value, \Modules\Committee\Enums\CommitteeMembershipStatus::CANCELLED->value])->count();
        $canManage = app(AuthorizationService::class)->can(auth()->user(), 'committee.manage', new AuthorizationContext($this->organizationId));
        $canManageMemberships = $canManage || app(AuthorizationService::class)->can(auth()->user(), 'committee.memberships.manage', new AuthorizationContext($this->organizationId));
        $scope = $term->committee->unit_id === null ? DesignationScope::CENTRAL : DesignationScope::UNIT;
        $designationOptions = Designation::query()->where('organization_id', $organization->id)->where('is_active', true)->where('scope', $scope->value)->orderBy('sort_order')->orderBy('name')->get();
        $search = trim($this->memberSearch);
        $memberOptions = collect();
        if ($canManageMemberships && $search !== '' && $this->memberId === '') {
            $memberOptions = Member::query()->where('organization_id', $organization->id)->with(['user', 'unit'])
                ->where(function ($query) use ($search): void {
                    $query->where('membership_number', 'like', '%' . $search . '%')
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%' . $search . '%')->orWhere('email', 'like', '%' . $search . '%'));
                })->orderBy('membership_number')->limit(10)->get();
        }
        return view('committee::livewire.committee-members', compact('term', 'canManage', 'canManageMemberships', 'designationOptions', 'memberOptions', 'formerMembershipCount'))
            ->layout('core::layouts.organization-admin', ['pageTitle' => 'Manage Committee Members']);
    }
}
