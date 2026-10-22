<?php

namespace Modules\Committee\Livewire;

use App\Support\Organization\CurrentOrganization;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Committee\Models\Committee;
use Modules\Committee\Models\CommitteeType;
use Modules\Committee\Models\CommitteeTerm;
use Modules\Committee\Models\CommitteeMembership;
use Modules\Committee\Models\Designation;
use Modules\Committee\Enums\DesignationScope;
use Modules\Committee\Services\CommitteeMembershipService;
use Modules\Member\Models\Member;
use Modules\Committee\Enums\CommitteeKind;
use Modules\Committee\Enums\CommitteeTermStatus;
use Modules\Committee\Services\CommitteeService;
use Modules\Committee\Services\CommitteeTermService;
use Modules\Core\Models\Unit;
use Modules\Core\Models\Organization;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Services\AuthorizationService;
use Modules\Core\Services\OrganizationModuleService;

class CommitteeIndex extends Component
{
    use WithPagination;

    public int $organizationId;

    public string $search = '';

    public bool $showCreateForm = false;
    public string $name = '';
    public string $kind = 'recurring';
    public string $committeeTypeId = '';
    public string $unitId = '';
    public string $parentCommitteeId = '';

    public ?int $selectedCommitteeId = null;
    public bool $showTermPanel = false;
    public string $termStartDate = '';
    public string $termEndDate = '';
    public ?int $editingTermId = null;

    public ?int $selectedTermId = null;
    public bool $showMembersPanel = false;
    public string $memberSearch = '';
    public string $selectedMemberLabel = '';
    public string $memberId = '';
    public string $designationId = '';

    public function mount(CurrentOrganization $currentOrganization, AuthorizationService $authorization, OrganizationModuleService $modules): void
    {
        $organization = $currentOrganization->get();
        $this->organizationId = (int) $organization->id;
        abort_unless($modules->isEnabled($organization, 'Committee'), 404);
        $context = new AuthorizationContext((int) $organization->id);
        abort_unless(auth()->user() && (
            $authorization->can(auth()->user(), 'committee.view', $context)
            || $authorization->can(auth()->user(), 'committee.manage', $context)
        ), 403);

        // Provide a small organization-scoped starter catalog so the required
        // type selector is usable immediately for newly configured organizations.
        if (! CommitteeType::query()->where('organization_id', $organization->id)->where('is_active', true)->exists()) {
            foreach ([
                ['name' => 'General Committee', 'code' => 'general'],
                ['name' => 'Executive Committee', 'code' => 'executive'],
                ['name' => 'Advisory Committee', 'code' => 'advisory'],
            ] as $type) {
                CommitteeType::query()->updateOrCreate(
                    ['organization_id' => $organization->id, 'code' => $type['code']],
                    ['name' => $type['name'], 'is_active' => true],
                );
            }
        }
    }

    public function updatingSearch(): void { $this->resetPage(); }

    public function updatedMemberSearch(): void
    {
        // A changed query invalidates any previously selected member.
        $this->memberId = '';
        $this->selectedMemberLabel = '';
    }

    public function selectMember(int $memberId): void
    {
        $this->authorizeMembershipManage();
        $member = Member::query()
            ->where('organization_id', $this->organization()->id)
            ->with('user')
            ->whereKey($memberId)
            ->firstOrFail();

        $this->memberId = (string) $member->id;
        $this->selectedMemberLabel = trim((string) $member->user?->name)
            . ' · ' . ($member->membership_number ?: ('Member #' . $member->id));
        $this->memberSearch = $this->selectedMemberLabel;
        $this->resetValidation('memberId');
    }

    public function clearSelectedMember(): void
    {
        $this->memberId = '';
        $this->selectedMemberLabel = '';
        $this->memberSearch = '';
    }

    public function create(): void
    {
        $this->authorizeManage();
        $this->resetValidation();
        $this->reset(['name', 'kind', 'committeeTypeId', 'unitId', 'parentCommitteeId']);
        $this->kind = 'recurring';
        $this->showCreateForm = true;
    }

    public function cancelCreate(): void
    {
        $this->showCreateForm = false;
        $this->resetValidation();
    }

    public function save(CommitteeService $service, CurrentOrganization $currentOrganization): void
    {
        $organization = $this->organization();
        $this->authorizeManage();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', 'in:recurring,temporary'],
            'committeeTypeId' => ['required', 'integer', 'exists:committee_types,id'],
            'unitId' => ['nullable', 'integer', 'exists:units,id'],
            'parentCommitteeId' => ['nullable', 'integer', 'exists:committees,id'],
        ]);

        try {
            $type = CommitteeType::query()
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->whereKey((int) $validated['committeeTypeId'])
                ->firstOrFail();

            $unitId = $validated['unitId'] !== '' ? (int) $validated['unitId'] : null;
            $parentId = $validated['parentCommitteeId'] !== '' ? (int) $validated['parentCommitteeId'] : null;

            if ($validated['kind'] === 'temporary') {
                $service->createTemporaryCommittee((int) $organization->id, (int) $type->id, $validated['name'], $unitId, $parentId);
            } else {
                $service->createRecurringCommittee((int) $organization->id, (int) $type->id, $validated['name'], $unitId, $parentId);
            }
        } catch (\Throwable $exception) {
            report($exception);
            $this->addError('name', 'The committee could not be saved. Check the selected type, unit, and parent committee.');
            return;
        }

        $this->showCreateForm = false;
        $this->reset(['name', 'kind', 'committeeTypeId', 'unitId', 'parentCommitteeId']);
        session()->flash('committee_message', 'Committee created successfully.');
        $this->resetPage();
    }

    public function manageTerms(int $committeeId): void
    {
        $this->authorizeManage();
        $committee = Committee::query()->where('organization_id', $this->organization()->id)->whereKey($committeeId)->firstOrFail();
        abort_unless($committee->kind === CommitteeKind::RECURRING, 422, 'Only recurring committees can have terms.');

        $this->selectedCommitteeId = (int) $committee->id;
        $this->showTermPanel = true;
        $this->resetTermForm();
        $this->resetValidation();
    }

    public function cancelTermEdit(): void
    {
        $this->resetTermForm();
        $this->resetValidation();
    }

    public function closeTermPanel(): void
    {
        $this->showTermPanel = false;
        $this->selectedCommitteeId = null;
        $this->resetTermForm();
        $this->resetValidation();
    }

    public function editTerm(int $termId): void
    {
        $this->authorizeManage();
        $committee = $this->selectedCommittee();
        $term = $committee->terms()->whereKey($termId)->firstOrFail();

        abort_if($term->status === CommitteeTermStatus::COMPLETED, 422, 'Completed committee terms cannot be edited.');

        $this->editingTermId = (int) $term->id;
        $this->termStartDate = $term->start_date->format('Y-m-d');
        $this->termEndDate = $term->end_date->format('Y-m-d');
        $this->resetValidation();
    }

    public function saveTerm(CommitteeService $service): void
    {
        $this->authorizeManage();
        $committee = $this->selectedCommittee();

        $validated = $this->validate([
            'termStartDate' => ['required', 'date'],
            'termEndDate' => ['required', 'date'],
        ]);

        if ($validated['termEndDate'] <= $validated['termStartDate']) {
            $this->addError('termEndDate', 'The end date must be after the start date.');
            return;
        }

        try {
            if ($this->editingTermId !== null) {
                $term = $committee->terms()->whereKey($this->editingTermId)->firstOrFail();
                $service->updateTerm($term, $validated['termStartDate'], $validated['termEndDate']);
                $message = 'Committee term updated successfully.';
            } else {
                $service->createTerm($committee, $validated['termStartDate'], $validated['termEndDate']);
                $message = 'Committee term created as a draft. Activate it when it is ready.';
            }
        } catch (\Throwable $exception) {
            report($exception);
            $this->addError('termEndDate', $exception instanceof \DomainException || $exception instanceof \InvalidArgumentException
                ? $exception->getMessage()
                : 'The committee term could not be saved. Check the dates and try again.');
            return;
        }

        $this->resetTermForm();
        session()->flash('committee_message', $message);
    }

    public function activateTerm(int $termId, CommitteeTermService $service): void
    {
        $this->authorizeManage();
        $term = $this->selectedCommittee()->terms()->whereKey($termId)->firstOrFail();

        try {
            $service->activate($term);
            session()->flash('committee_message', 'Committee term activated successfully.');
        } catch (\Throwable $exception) {
            report($exception);
            session()->flash('committee_error', $exception instanceof \DomainException
                ? $exception->getMessage()
                : 'The committee term could not be activated.');
        }
    }

    public function completeTerm(int $termId, CommitteeTermService $service): void
    {
        $this->authorizeManage();
        $term = $this->selectedCommittee()->terms()->whereKey($termId)->firstOrFail();

        try {
            $service->complete($term);
            session()->flash('committee_message', 'Committee term completed successfully.');
        } catch (\Throwable $exception) {
            report($exception);
            session()->flash('committee_error', $exception instanceof \DomainException
                ? $exception->getMessage()
                : 'The committee term could not be completed.');
        }
    }


    public function manageMembers(int $termId): void
    {
        $this->authorizeMembershipManage();
        $term = CommitteeTerm::query()
            ->whereHas('committee', fn ($query) => $query->where('organization_id', $this->organization()->id))
            ->with('committee')
            ->whereKey($termId)
            ->firstOrFail();

        abort_unless(($term->status?->value ?? (string) $term->status) === 'active', 422, 'Members can only be managed for an active committee term.');

        $this->selectedTermId = (int) $term->id;
        $this->showMembersPanel = true;
        $this->memberSearch = '';
        $this->selectedMemberLabel = '';
        $this->memberId = '';
        $this->designationId = '';
        $this->resetValidation();
    }

    public function closeMembersPanel(): void
    {
        $this->showMembersPanel = false;
        $this->selectedTermId = null;
        $this->memberSearch = '';
        $this->selectedMemberLabel = '';
        $this->memberId = '';
        $this->designationId = '';
        $this->resetValidation();
    }

    public function addCommitteeMember(CommitteeMembershipService $service): void
    {
        $this->authorizeMembershipManage();
        $term = $this->selectedTerm();
        abort_unless(($term->status?->value ?? (string) $term->status) === 'active', 422, 'Members can only be added to an active committee term.');

        $validated = $this->validate([
            'memberId' => ['required', 'integer'],
            'designationId' => ['required', 'integer'],
        ]);

        $member = Member::query()
            ->where('organization_id', $this->organization()->id)
            ->whereKey((int) $validated['memberId'])
            ->firstOrFail();
        $designation = Designation::query()
            ->where('organization_id', $this->organization()->id)
            ->where('is_active', true)
            ->whereKey((int) $validated['designationId'])
            ->firstOrFail();

        try {
            $service->addMember($term, $member, $designation);
        } catch (\Throwable $exception) {
            report($exception);
            $this->addError('memberId', $exception instanceof \DomainException
                ? $exception->getMessage()
                : 'The member could not be assigned. Check the member and designation.');
            return;
        }

        $this->memberId = '';
        $this->selectedMemberLabel = '';
        $this->designationId = '';
        $this->memberSearch = '';
        session()->flash('committee_message', 'Member assigned to the committee successfully.');
    }

    public function endCommitteeMember(int $membershipId, CommitteeMembershipService $service): void
    {
        $this->authorizeMembershipManage();
        $term = $this->selectedTerm();
        $membership = $term->memberships()->whereKey($membershipId)->firstOrFail();

        try {
            $service->end($membership);
            session()->flash('committee_message', 'Committee membership ended.');
        } catch (\Throwable $exception) {
            report($exception);
            session()->flash('committee_error', $exception instanceof \DomainException ? $exception->getMessage() : 'The membership could not be ended.');
        }
    }

    private function selectedTerm(): CommitteeTerm
    {
        abort_unless($this->selectedTermId !== null, 404);
        return CommitteeTerm::query()
            ->whereHas('committee', fn ($query) => $query->where('organization_id', $this->organization()->id))
            ->with('committee')
            ->whereKey($this->selectedTermId)
            ->firstOrFail();
    }

    private function authorizeMembershipManage(): void
    {
        $organization = $this->organization();
        $context = new AuthorizationContext((int) $organization->id);
        $user = auth()->user();
        abort_unless($user && (
            app(AuthorizationService::class)->can($user, 'committee.memberships.manage', $context)
            || app(AuthorizationService::class)->can($user, 'committee.manage', $context)
        ), 403);
    }

    private function selectedCommittee(): Committee
    {
        abort_unless($this->selectedCommitteeId !== null, 404);
        return Committee::query()->where('organization_id', $this->organization()->id)->whereKey($this->selectedCommitteeId)->firstOrFail();
    }

    private function resetTermForm(): void
    {
        $this->termStartDate = '';
        $this->termEndDate = '';
        $this->editingTermId = null;
    }

    private function authorizeManage(): void
    {
        $organization = $this->organization();
        $context = new AuthorizationContext((int) $organization->id);
        abort_unless(auth()->user() && app(AuthorizationService::class)->can(auth()->user(), 'committee.manage', $context), 403);
    }

    private function organization(): Organization
    {
        $organization = Organization::query()->findOrFail($this->organizationId);
        abort_unless(app(OrganizationModuleService::class)->isEnabled($organization, 'Committee'), 404);
        return $organization;
    }

    public function render(CurrentOrganization $currentOrganization): View
    {
        $organization = $this->organization();
        $context = new AuthorizationContext((int) $organization->id);
        abort_unless(auth()->user() && (
            app(AuthorizationService::class)->can(auth()->user(), 'committee.view', $context)
            || app(AuthorizationService::class)->can(auth()->user(), 'committee.manage', $context)
        ), 403);
        $types = CommitteeType::query()->where('organization_id', $organization->id)->where('is_active', true)->orderBy('name')->get();
        $units = Unit::query()->where('organization_id', $organization->id)->orderBy('name')->get();
        $parentCommittees = Committee::query()->where('organization_id', $organization->id)->where('status', '!=', 'archived')->orderBy('name')->get();
        $canManage = app(AuthorizationService::class)->can(auth()->user(), 'committee.manage', new AuthorizationContext((int) $organization->id));

        $committees = Committee::query()
            ->where('organization_id', $organization->id)
            ->with(['type', 'unit', 'terms'])
            ->when(trim($this->search) !== '', function ($query): void {
                $term = '%' . trim($this->search) . '%';
                $query->where('name', 'like', $term);
            })
            ->orderBy('name')
            ->paginate(15);

        $selectedCommittee = $this->showTermPanel && $this->selectedCommitteeId !== null
            ? Committee::query()->where('organization_id', $organization->id)->with('terms')->whereKey($this->selectedCommitteeId)->first()
            : null;

        $selectedTerm = $this->showMembersPanel && $this->selectedTermId !== null
            ? CommitteeTerm::query()->whereHas('committee', fn ($query) => $query->where('organization_id', $organization->id))->with(['committee', 'memberships.member.user', 'memberships.member.unit', 'memberships.designation'])->whereKey($this->selectedTermId)->first()
            : null;
        $memberOptions = collect();
        $designationOptions = collect();
        if ($selectedTerm) {
            $searchTerm = trim($this->memberSearch);
            $memberOptions = Member::query()
                ->where('organization_id', $organization->id)
                ->with(['user', 'unit'])
                ->when($searchTerm !== '' && $this->memberId === '', function ($query) use ($searchTerm): void {
                    $query->where(function ($members) use ($searchTerm): void {
                        $members->where('membership_number', 'like', '%' . $searchTerm . '%')
                            ->orWhereHas('user', function ($user) use ($searchTerm): void {
                                $user->where('name', 'like', '%' . $searchTerm . '%')
                                    ->orWhere('email', 'like', '%' . $searchTerm . '%');
                            });
                    });
                })
                ->when($searchTerm === '' || $this->memberId !== '', fn ($query) => $query->whereRaw('1 = 0'))
                ->orderBy('membership_number')
                ->limit(10)
                ->get();
            $scope = $selectedTerm->committee->unit_id === null ? DesignationScope::CENTRAL : DesignationScope::UNIT;
            $designationOptions = Designation::query()->where('organization_id', $organization->id)->where('is_active', true)->where('scope', $scope->value)->orderBy('name')->get();
        }
        $canManageMemberships = $canManage || app(AuthorizationService::class)->can(auth()->user(), 'committee.memberships.manage', new AuthorizationContext((int) $organization->id));

        return view('committee::livewire.committees', compact('committees', 'types', 'units', 'parentCommittees', 'canManage', 'selectedCommittee', 'selectedTerm', 'memberOptions', 'designationOptions', 'canManageMemberships'))
            ->layout('core::layouts.organization-admin', ['pageTitle' => 'Committees']);
    }
}
