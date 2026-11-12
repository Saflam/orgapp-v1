<?php

namespace Modules\Member\Livewire;

use App\Support\Organization\CurrentOrganization;
use DomainException;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Core\Services\AuthorizationService;
use Modules\Member\Enums\MemberUnitTransferApprovalSide;
use Modules\Member\Enums\MemberUnitTransferStatus;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberUnitTransfer;
use Modules\Member\Services\MemberUnitTransferService;

class MemberUnitTransferManagement extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public bool $showCreateModal = false;
    public bool $showRejectModal = false;
    public ?int $memberId = null;
    public string $memberSearch = '';
    public ?int $targetUnitId = null;
    public bool $spouseIncluded = true;
    public ?int $rejectTransferId = null;
    public string $rejectSide = 'outgoing';
    public string $rejectionReason = '';
    public string $successMessage = '';

    protected string $paginationTheme = 'tailwind';

    public function mount(CurrentOrganization $currentOrganization): void
    {
        $organization = $currentOrganization->get();
        abort_unless($organization instanceof Organization, 404);
        abort_unless($this->authorizedUnitIds('member.unit-transfer.view') !== [], 403);

        $prefilledMemberId = request()->query('member');
        if ($prefilledMemberId !== null) {
            $this->openCreateModal();
            $this->selectMember((int) $prefilledMemberId);
        }
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }

    public function openCreateModal(): void
    {
        abort_unless($this->authorizedUnitIds('member.unit-transfer.submit') !== [], 403);
        $this->resetValidation();
        $this->reset(['memberId', 'targetUnitId', 'memberSearch']);
        $this->spouseIncluded = true;
        $this->showCreateModal = true;
    }

    public function updatedMemberSearch(): void
    {
        // Search results are queried on demand and limited in render().
    }

    public function selectMember(int $memberId): void
    {
        $member = Member::query()
            ->where('organization_id', $this->organization()->id)
            ->whereIn('unit_id', $this->authorizedUnitIds('member.unit-transfer.submit'))
            ->with(['user', 'unit'])
            ->findOrFail($memberId);

        $this->memberId = (int) $member->id;
        $this->memberSearch = trim(($member->user?->name ?? 'Member') . ' — ' . ($member->membership_number ?? 'No membership number'));
        $this->resetValidation('memberId');
    }

    public function submitTransfer(MemberUnitTransferService $service): void
    {
        $data = $this->validate([
            'memberId' => ['required', 'integer'],
            'targetUnitId' => ['required', 'integer'],
            'spouseIncluded' => ['boolean'],
        ]);

        $organization = $this->organization();
        $member = Member::query()
            ->where('organization_id', $organization->id)
            ->whereIn('unit_id', $this->authorizedUnitIds('member.unit-transfer.submit'))
            ->findOrFail($data['memberId']);

        try {
            $service->submit($member, (int) $data['targetUnitId'], (bool) $data['spouseIncluded']);
        } catch (DomainException $exception) {
            $this->addError('transfer', $exception->getMessage());
            return;
        }

        $this->showCreateModal = false;
        $this->reset(['memberId', 'targetUnitId', 'memberSearch']);
        $this->spouseIncluded = true;
        $this->successMessage = 'Unit transfer request submitted for outgoing and incoming unit approval.';
        $this->resetPage();
    }

    public function approve(int $transferId, string $side, MemberUnitTransferService $service): void
    {
        $transfer = $this->findVisibleTransfer($transferId);
        $approvalSide = MemberUnitTransferApprovalSide::tryFrom($side);
        abort_unless($approvalSide !== null, 422);

        try {
            $service->approve($transfer, auth()->user(), $approvalSide);
        } catch (DomainException $exception) {
            $this->addError('action', $exception->getMessage());
            return;
        }

        $this->successMessage = 'Unit transfer approval recorded.';
    }

    public function openRejectModal(int $transferId, string $side): void
    {
        $this->findVisibleTransfer($transferId);
        abort_unless(MemberUnitTransferApprovalSide::tryFrom($side) !== null, 422);
        $this->resetValidation();
        $this->rejectTransferId = $transferId;
        $this->rejectSide = $side;
        $this->rejectionReason = '';
        $this->showRejectModal = true;
    }

    public function reject(MemberUnitTransferService $service): void
    {
        $data = $this->validate([
            'rejectTransferId' => ['required', 'integer'],
            'rejectSide' => ['required', 'in:outgoing,incoming'],
            'rejectionReason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $transfer = $this->findVisibleTransfer((int) $data['rejectTransferId']);

        try {
            $service->reject($transfer, auth()->user(), MemberUnitTransferApprovalSide::from($data['rejectSide']), $data['rejectionReason']);
        } catch (DomainException $exception) {
            $this->addError('reject', $exception->getMessage());
            return;
        }

        $this->showRejectModal = false;
        $this->successMessage = 'Unit transfer request rejected.';
    }

    public function complete(int $transferId, MemberUnitTransferService $service): void
    {
        $transfer = $this->findVisibleTransfer($transferId);

        try {
            $service->complete($transfer, auth()->user());
        } catch (DomainException $exception) {
            $this->addError('action', $exception->getMessage());
            return;
        }

        $this->successMessage = 'Unit transfer completed. Member unit history has been updated.';
    }

    public function render(AuthorizationService $authorization): View
    {
        $organization = $this->organization();
        $viewUnitIds = $this->authorizedUnitIds('member.unit-transfer.view');
        $submitUnitIds = $this->authorizedUnitIds('member.unit-transfer.submit');
        $units = Unit::query()->where('organization_id', $organization->id)->where('is_active', true)->orderBy('name')->get();
        $memberResults = collect();
        if (trim($this->memberSearch) !== '' && $this->memberId === null) {
            $term = '%' . trim($this->memberSearch) . '%';
            $memberResults = Member::query()
                ->where('members.organization_id', $organization->id)
                ->whereIn('members.unit_id', $submitUnitIds)
                ->whereHas('user')
                ->where(function ($query) use ($term): void {
                    $query->where('members.membership_number', 'like', $term)
                        ->orWhereHas('user', function ($user) use ($term): void {
                            $user->where('name', 'like', $term)->orWhere('email', 'like', $term);
                        });
                })
                ->with(['user', 'unit'])
                ->orderBy('members.id', 'desc')
                ->limit(10)
                ->get();
        }
        $selectedMember = $this->memberId
            ? Member::query()->where('organization_id', $organization->id)
                ->whereIn('unit_id', $submitUnitIds)->with(['user', 'unit'])->find($this->memberId)
            : null;

        $transfersQuery = MemberUnitTransfer::query()
            ->where('organization_id', $organization->id)
            ->where(function ($query) use ($viewUnitIds): void {
                $query->whereIn('from_unit_id', $viewUnitIds)->orWhereIn('to_unit_id', $viewUnitIds);
            })
            ->with(['member.user', 'spouse.user', 'fromUnit', 'toUnit', 'approvals.unit', 'approvals.decidedBy'])
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->when(trim($this->search) !== '', function ($query): void {
                $term = '%' . trim($this->search) . '%';
                $query->where(function ($nested) use ($term): void {
                    $nested->whereHas('member.user', fn ($user) => $user->where('name', 'like', $term))
                        ->orWhereHas('member', fn ($member) => $member->where('membership_number', 'like', $term))
                        ->orWhereHas('spouse.user', fn ($user) => $user->where('name', 'like', $term))
                        ->orWhereHas('fromUnit', fn ($unit) => $unit->where('name', 'like', $term))
                        ->orWhereHas('toUnit', fn ($unit) => $unit->where('name', 'like', $term));
                });
            })
            ->latest('submitted_at');

        return view('member::livewire.unit-transfer-management', [
            'transfers' => $transfersQuery->paginate(10),
            'units' => $units,
            'memberResults' => $memberResults,
            'selectedMember' => $selectedMember,
            'canSubmit' => $submitUnitIds !== [],
            'canApproveOutgoing' => $this->hasUnitPermission('member.unit-transfer.approve', $viewUnitIds),
            'canRejectOutgoing' => $this->hasUnitPermission('member.unit-transfer.reject', $viewUnitIds),
            'canComplete' => $this->hasUnitPermission('member.unit-transfer.complete', $viewUnitIds),
        ])->layout('core::layouts.organization-admin', [
            'pageTitle' => 'Unit Transfers',
            'layoutOrganization' => $organization,
        ]);
    }

    public function canDecideTransfer(MemberUnitTransfer $transfer, string $permission, string $side): bool
    {
        $approval = $transfer->approvals->first(fn ($item) => $item->side->value === $side);
        return $approval !== null && app(AuthorizationService::class)->can(
            auth()->user(),
            $permission,
            new AuthorizationContext((int) $transfer->organization_id, (int) $approval->unit_id),
        );
    }

    public function canCompleteTransfer(MemberUnitTransfer $transfer): bool
    {
        $authorization = app(AuthorizationService::class);
        $user = auth()->user();
        return $authorization->can($user, 'member.unit-transfer.complete', new AuthorizationContext((int) $transfer->organization_id, (int) $transfer->from_unit_id))
            || $authorization->can($user, 'member.unit-transfer.complete', new AuthorizationContext((int) $transfer->organization_id, (int) $transfer->to_unit_id));
    }

    private function findVisibleTransfer(int $id): MemberUnitTransfer
    {
        $unitIds = $this->authorizedUnitIds('member.unit-transfer.view');
        return MemberUnitTransfer::query()
            ->where('organization_id', $this->organization()->id)
            ->where(function ($query) use ($unitIds): void {
                $query->whereIn('from_unit_id', $unitIds)->orWhereIn('to_unit_id', $unitIds);
            })
            ->with(['approvals', 'member', 'spouse'])
            ->findOrFail($id);
    }

    private function authorizedUnitIds(string $permission): array
    {
        $organization = $this->organization();
        $user = auth()->user();
        abort_unless($user, 403);
        $authorization = app(AuthorizationService::class);
        $organizationContext = new AuthorizationContext((int) $organization->id);

        if ($authorization->can($user, $permission, $organizationContext)) {
            return Unit::query()->where('organization_id', $organization->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return Unit::query()->where('organization_id', $organization->id)->get(['id'])
            ->filter(fn (Unit $unit): bool => $authorization->can(
                $user,
                $permission,
                new AuthorizationContext((int) $organization->id, (int) $unit->id),
            ))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function hasUnitPermission(string $permission, array $unitIds): bool
    {
        $user = auth()->user();
        $organization = $this->organization();
        $authorization = app(AuthorizationService::class);
        if ($authorization->can($user, $permission, new AuthorizationContext((int) $organization->id))) {
            return true;
        }
        foreach ($unitIds as $unitId) {
            if ($authorization->can($user, $permission, new AuthorizationContext((int) $organization->id, (int) $unitId))) {
                return true;
            }
        }
        return false;
    }

    private function organization(): Organization
    {
        $organization = app(CurrentOrganization::class)->get();
        abort_unless($organization instanceof Organization, 404);
        return $organization;
    }
}
