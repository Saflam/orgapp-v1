<?php

namespace Modules\Finance\Livewire;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Core\Services\AuthorizationService;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceSettlement;
use Modules\Finance\Models\FinanceSettlementObligation;
use Modules\Finance\Services\FinanceScopeService;
use Modules\Finance\Services\FinanceSettlementService;

class FinanceSettlementManagement extends Component
{
    public Organization $organization;
    public ?int $scopeUnitId = null;
    public ?int $obligationId = null;
    public string $amount = '';
    public string $transactionDate = '';
    public ?int $payerCashAccountId = null;
    public ?int $payeeCashAccountId = null;
    public ?string $reference = null;
    public ?int $rejectSettlementId = null;
    public string $rejectionReason = '';

    public function mount(CurrentOrganization $currentOrganization): void
    {
        $this->organization = $currentOrganization->get();
        abort_unless($this->organization instanceof Organization && $this->organization->exists, 404);
        $this->transactionDate = now()->toDateString();
    }

    public function openSettlementForm(int $obligationId): void
    {
        $obligation = FinanceSettlementObligation::query()
            ->where('organization_id', $this->organization->id)
            ->findOrFail($obligationId);

        $pendingAmount = FinanceSettlement::query()
            ->where('finance_settlement_obligation_id', $obligation->id)
            ->where('status', 'pending_confirmation')
            ->sum('amount');

        $available = max(0, (float) $obligation->outstanding_amount - (float) $pendingAmount);

        $this->resetValidation();
        $this->obligationId = $obligation->id;
        $this->amount = number_format($available, 2, '.', '');
        $this->payerCashAccountId = null;
        $this->payeeCashAccountId = null;
        $this->reference = null;
        $this->transactionDate = now()->toDateString();
    }

    public function recordSettlement(
        AuthorizationService $authorization,
        FinanceSettlementService $settlements,
    ): void {
        $this->validate([
            'obligationId' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'transactionDate' => ['required', 'date_format:Y-m-d'],
            'payerCashAccountId' => ['required', 'integer'],
            'payeeCashAccountId' => ['required', 'integer', 'different:payerCashAccountId'],
            'reference' => ['nullable', 'string', 'max:150'],
        ]);

        $obligation = FinanceSettlementObligation::query()
            ->where('organization_id', $this->organization->id)
            ->findOrFail($this->obligationId);

        abort_unless($this->canManageScope($authorization, $obligation->debtor_scope_type, $obligation->debtor_scope_id), 403);

        $settlements->record(
            $this->organization,
            $obligation,
            $this->amount,
            $this->transactionDate,
            $this->payerCashAccountId,
            $this->payeeCashAccountId,
            auth()->id(),
            $this->reference,
        );

        $this->reset(['obligationId', 'amount', 'payerCashAccountId', 'payeeCashAccountId', 'reference']);
        $this->transactionDate = now()->toDateString();
        session()->flash('settlement-status', 'Settlement recorded and its Finance journal posted. It is awaiting confirmation.');
    }

    public function confirmSettlement(
        int $settlementId,
        AuthorizationService $authorization,
        FinanceSettlementService $settlements,
    ): void {
        $settlement = $this->settlement($settlementId);
        $obligation = $settlement->obligation;
        abort_unless($this->canConfirmScope($authorization, $obligation->creditor_scope_type, $obligation->creditor_scope_id), 403);

        $settlements->confirm($this->organization, $settlement, auth()->id());
        session()->flash('settlement-status', 'Settlement receipt confirmed and the outstanding balance updated.');
    }

    public function rejectSettlement(
        int $settlementId,
        AuthorizationService $authorization,
        FinanceSettlementService $settlements,
    ): void {
        $this->validate(['rejectionReason' => ['required', 'string', 'min:3', 'max:1000']]);
        $settlement = $this->settlement($settlementId);
        $obligation = $settlement->obligation;
        abort_unless($this->canConfirmScope($authorization, $obligation->creditor_scope_type, $obligation->creditor_scope_id), 403);

        $settlements->reject($this->organization, $settlement, $this->rejectionReason, auth()->id());
        $this->reset(['rejectSettlementId', 'rejectionReason']);
        session()->flash('settlement-status', 'Settlement rejected. Its Finance journal was reversed.');
    }

    public function render(
        AuthorizationService $authorization,
        FinanceScopeService $scopes,
    ): View {
        $visibleScopes = $this->visibleScopes($authorization);
        abort_unless($visibleScopes->isNotEmpty(), 403);

        if ($this->scopeUnitId !== null) {
            abort_unless($visibleScopes->contains(fn (array $scope): bool => $scope['type'] === 'unit' && $scope['id'] === $this->scopeUnitId), 403);
        }

        $query = FinanceSettlementObligation::query()
            ->where('organization_id', $this->organization->id)
            ->where('outstanding_amount', '>', 0)
            ->with(['payment', 'paymentLine', 'settlements']);

        $query->where(function ($outer) use ($visibleScopes): void {
            foreach ($visibleScopes as $scope) {
                $outer->orWhere(function ($q) use ($scope): void {
                    $q->where(function ($side) use ($scope): void {
                        $side->where('debtor_scope_type', $scope['type'])->where('debtor_scope_id', $scope['id']);
                    })->orWhere(function ($side) use ($scope): void {
                        $side->where('creditor_scope_type', $scope['type'])->where('creditor_scope_id', $scope['id']);
                    });
                });
            }
        });

        if ($this->scopeUnitId !== null) {
            $query->where(function ($q): void {
                $q->where(function ($side): void {
                    $side->where('debtor_scope_type', 'unit')->where('debtor_scope_id', $this->scopeUnitId);
                })->orWhere(function ($side): void {
                    $side->where('creditor_scope_type', 'unit')->where('creditor_scope_id', $this->scopeUnitId);
                });
            });
        }

        $scope = $scopes->organizationScope($this->organization);
        $cashAccounts = FinanceAccount::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $scope->id)
            ->where('is_active', true)
            ->where('is_cash_account', true)
            ->orderBy('code')
            ->get();

        $settlementIds = FinanceSettlement::query()
            ->where('organization_id', $this->organization->id)
            ->where('status', 'pending_confirmation')
            ->pluck('id');

        $pending = FinanceSettlement::query()
            ->whereIn('id', $settlementIds)
            ->with(['obligation', 'journal', 'payerCashAccount', 'payeeCashAccount'])
            ->latest('id')
            ->get()
            ->filter(function (FinanceSettlement $settlement) use ($visibleScopes): bool {
                $obligation = $settlement->obligation;
                if (! $this->scopeIsVisible($visibleScopes, $obligation->creditor_scope_type, $obligation->creditor_scope_id)) {
                    return false;
                }
                return $this->scopeUnitId === null
                    || ($obligation->creditor_scope_type === 'unit'
                        && (int) $obligation->creditor_scope_id === $this->scopeUnitId);
            });

        $units = Unit::query()->where('organization_id', $this->organization->id)->orderBy('name')->get();

        $obligations = $query->orderBy('transaction_date')->orderBy('id')->get();
        $manageableObligationIds = $obligations->filter(fn (FinanceSettlementObligation $item): bool =>
            $this->canManageScope($authorization, $item->debtor_scope_type, $item->debtor_scope_id)
        )->pluck('id')->all();
        $confirmableSettlementIds = $pending->filter(fn (FinanceSettlement $item): bool =>
            $this->canConfirmScope($authorization, $item->obligation->creditor_scope_type, $item->obligation->creditor_scope_id)
        )->pluck('id')->all();

        return view('finance::livewire.settlements.manage', [
            'obligations' => $obligations,
            'pendingSettlements' => $pending,
            'manageableObligationIds' => $manageableObligationIds,
            'confirmableSettlementIds' => $confirmableSettlementIds,
            'cashAccounts' => $cashAccounts,
            'units' => $units,
            'visibleScopes' => $visibleScopes,
        ])->layout('core::layouts.organization-admin', [
            'pageTitle' => 'Central / Unit Settlements',
            'layoutOrganization' => $this->organization,
        ]);
    }

    private function settlement(int $id): FinanceSettlement
    {
        return FinanceSettlement::query()
            ->where('organization_id', $this->organization->id)
            ->with('obligation')
            ->findOrFail($id);
    }

    private function visibleScopes(AuthorizationService $authorization): Collection
    {
        $user = auth()->user();
        $scopes = collect();

        $organizationContext = new AuthorizationContext((int) $this->organization->id);
        $organizationCanView = $authorization->can($user, 'finance.settlement.view', $organizationContext);
        if ($organizationCanView) {
            $scopes->push(['type' => 'organization', 'id' => (int) $this->organization->id, 'label' => 'Central']);
        }

        foreach (Unit::query()->where('organization_id', $this->organization->id)->get() as $unit) {
            if ($organizationCanView || $authorization->can(
                $user,
                'finance.settlement.view',
                new AuthorizationContext((int) $this->organization->id, (int) $unit->id),
            )) {
                $scopes->push(['type' => 'unit', 'id' => (int) $unit->id, 'label' => $unit->name]);
            }
        }

        return $scopes;
    }

    private function canManageScope(AuthorizationService $authorization, string $type, ?int $id): bool
    {
        return $authorization->can(
            auth()->user(),
            'finance.settlement.manage',
            new AuthorizationContext((int) $this->organization->id),
        ) || $authorization->can(
            auth()->user(),
            'finance.settlement.manage',
            $this->authorizationContext($type, $id),
        );
    }

    private function canConfirmScope(AuthorizationService $authorization, string $type, ?int $id): bool
    {
        return $authorization->can(
            auth()->user(),
            'finance.settlement.confirm',
            new AuthorizationContext((int) $this->organization->id),
        ) || $authorization->can(
            auth()->user(),
            'finance.settlement.confirm',
            $this->authorizationContext($type, $id),
        );
    }

    private function authorizationContext(string $type, ?int $id): object
    {
        if ($type === 'organization') {
            return new AuthorizationContext((int) $this->organization->id);
        }
        abort_if($id === null, 404);
        abort_unless($this->organization->units()->whereKey($id)->exists(), 404);
        return new AuthorizationContext((int) $this->organization->id, (int) $id);
    }

    private function scopeIsVisible(Collection $scopes, string $type, ?int $id): bool
    {
        return $scopes->contains(fn (array $scope): bool => $scope['type'] === $type && $scope['id'] === $id);
    }
}
