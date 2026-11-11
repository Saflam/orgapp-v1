<?php

namespace Modules\Finance\Livewire;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationService;
use Modules\Committee\Models\Committee;
use Modules\Finance\Enums\FinancePeriodStatus;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceJournal;
use Modules\Finance\Models\FinancePeriod;
use Modules\Finance\Services\FinanceJournalService;
use Modules\Finance\Services\FinanceScopeService;
use InvalidArgumentException;

class FinanceJournalManagement extends Component
{
    use WithPagination;
    public Organization $organization;

    public string $transactionDate = '';
    public ?int $periodId = null;
    public ?int $unitId = null;
    public string $description = '';
    public array $lines = [];

    public string $search = '';
    public ?int $filterPeriodId = null;
    public ?int $filterUnitId = null;
    public string $fromDate = '';
    public string $toDate = '';

    public ?int $reversalJournalId = null;
    public string $reversalDate = '';
    public ?int $reversalPeriodId = null;
    public string $reversalReason = '';

    public string $successMessage = '';

    public function mount(
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
    ): void {
        $this->organization = $currentOrganization->get();

        abort_unless(
            $this->organization instanceof Organization && $this->organization->exists,
            404,
        );

        $this->transactionDate = now()->toDateString();
        $this->resetEntryForm();
        $this->ensureCanView($authorization);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPeriodId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterUnitId(): void
    {
        $this->resetPage();
    }

    public function updatedFromDate(): void
    {
        $this->resetPage();
    }

    public function updatedToDate(): void
    {
        $this->resetPage();
    }

    public function addLine(): void
    {
        if (count($this->lines) >= 50) {
            throw ValidationException::withMessages([
                'lines' => 'A journal cannot contain more than 50 lines.',
            ]);
        }

        $this->lines[] = $this->emptyLine();
    }

    public function updatedLines(mixed $value, string $key): void
    {
        if (str_ends_with($key, '.cost_center_type')) {
            $index = (int) explode('.', $key, 2)[0];

            if (isset($this->lines[$index])) {
                $this->lines[$index]['cost_center_id'] = null;
            }
        }
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) <= 2) {
            throw ValidationException::withMessages([
                'lines' => 'A journal must contain at least two lines.',
            ]);
        }

        if (array_key_exists($index, $this->lines)) {
            unset($this->lines[$index]);
            $this->lines = array_values($this->lines);
        }
    }

    public function postJournal(
        FinanceJournalService $journals,
        FinanceScopeService $scopes,
        AuthorizationService $authorization,
    ): void {
        $this->successMessage = '';
        $this->ensureCanPost($authorization);

        $validated = $this->validate([
            'transactionDate' => ['required', 'date_format:Y-m-d'],
            'periodId' => ['required', 'integer'],
            'unitId' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:2', 'max:50'],
            'lines.*.finance_account_id' => ['required', 'integer'],
            'lines.*.cost_center_type' => ['required', 'in:none,organization,unit,committee'],
            'lines.*.cost_center_id' => ['nullable', 'integer', 'min:1'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
        ]);

        foreach ($validated['lines'] as $index => $line) {
            $centerType = $line['cost_center_type'] ?? 'none';

            if (in_array($centerType, ['unit', 'committee'], true)
                && blank($line['cost_center_id'] ?? null)) {
                throw ValidationException::withMessages([
                    "lines.$index.cost_center_id" => 'Select the unit or committee for this cost centre.',
                ]);
            }

            if ($centerType === 'committee' && ! Schema::hasTable('committees')) {
                throw ValidationException::withMessages([
                    "lines.$index.cost_center_type" => 'Committee cost centres are unavailable.',
                ]);
            }
        }

        $scope = $scopes->organizationScope($this->organization);

        $period = FinancePeriod::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $scope->id)
            ->findOrFail($validated['periodId']);

        $lines = array_map(
            fn (array $line): array => [
                'finance_account_id' => (int) $line['finance_account_id'],
                'cost_center_type' => ($line['cost_center_type'] ?? 'none') === 'none'
                    ? null
                    : $line['cost_center_type'],
                'cost_center_id' => in_array(($line['cost_center_type'] ?? 'none'), ['unit', 'committee'], true)
                    && ! blank($line['cost_center_id'] ?? null)
                        ? (int) $line['cost_center_id']
                        : null,
                'description' => blank($line['description'] ?? null) ? null : $line['description'],
                'debit' => $line['debit'],
                'credit' => $line['credit'],
            ],
            $validated['lines'],
        );

        try {
            $journal = $journals->post($this->organization, $scope, $period, [
                'transaction_date' => $validated['transactionDate'],
                'unit_id' => $validated['unitId'] ?: null,
                'description' => blank($validated['description'] ?? null) ? null : $validated['description'],
                'lines' => $lines,
            ]);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'journal' => $exception->getMessage(),
            ]);
        }

        $this->successMessage = sprintf(
            'Journal %s posted successfully.',
            $journal->journal_number,
        );

        $this->resetEntryForm();
    }

    public function prepareReversal(
        int $journalId,
        AuthorizationService $authorization,
        FinanceScopeService $scopes,
    ): void {
        $this->ensureCanReverse($authorization);

        $scope = $scopes->organizationScope($this->organization);
        $journal = FinanceJournal::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $scope->id)
            ->findOrFail($journalId);

        abort_unless(
            $journal->isPosted() && ! $journal->isReversal() && ! $journal->reversal()->exists()
                && ! in_array($journal->source_type, ['finance_sales_invoice', 'finance_purchase_bill', 'finance_payment'], true),
            422,
            'This journal cannot be reversed.',
        );

        $this->reversalJournalId = $journal->id;
        $this->reversalDate = now()->toDateString();
        $this->reversalPeriodId = null;
        $this->reversalReason = '';
        $this->resetValidation();
    }

    public function cancelReversal(): void
    {
        $this->reversalJournalId = null;
        $this->reversalDate = '';
        $this->reversalPeriodId = null;
        $this->reversalReason = '';
        $this->resetValidation();
    }

    public function reverseJournal(
        FinanceJournalService $journals,
        FinanceScopeService $scopes,
        AuthorizationService $authorization,
    ): void {
        $this->ensureCanReverse($authorization);

        $validated = $this->validate([
            'reversalJournalId' => ['required', 'integer'],
            'reversalDate' => ['required', 'date_format:Y-m-d'],
            'reversalPeriodId' => ['required', 'integer'],
            'reversalReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $scope = $scopes->organizationScope($this->organization);
        $original = FinanceJournal::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $scope->id)
            ->findOrFail($validated['reversalJournalId']);

        $period = FinancePeriod::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $scope->id)
            ->findOrFail($validated['reversalPeriodId']);

        try {
            $reversal = $journals->reverse(
                $this->organization,
                $scope,
                $original,
                $period,
                $validated['reversalDate'],
                $validated['reversalReason'],
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'reversalReason' => $exception->getMessage(),
            ]);
        }

        $this->successMessage = sprintf(
            'Journal %s reversed successfully by %s.',
            $original->journal_number,
            $reversal->journal_number,
        );

        $this->cancelReversal();
    }

    public function resetEntryForm(): void
    {
        $this->periodId = null;
        $this->unitId = null;
        $this->description = '';
        $this->lines = [
            $this->emptyLine(),
            $this->emptyLine(),
        ];
        $this->resetValidation();
    }

    public function render(
        AuthorizationService $authorization,
        FinanceScopeService $scopes,
    ): View {
        $this->ensureCanView($authorization);

        $scope = $scopes->organizationScope($this->organization);

        $accounts = FinanceAccount::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $scope->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $openPeriods = FinancePeriod::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $scope->id)
            ->where('status', FinancePeriodStatus::OPEN->value)
            ->orderByDesc('starts_on')
            ->get();

        $allPeriods = FinancePeriod::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $scope->id)
            ->orderByDesc('starts_on')
            ->get();

        $units = $this->organization->units()
            ->orderBy('name')
            ->get();

        $hasCommitteeTable = Schema::hasTable('committees');
        $committees = collect();
        if ($hasCommitteeTable) {
            $committees = Committee::query()
                ->where('organization_id', $this->organization->id)
                ->orderBy('name')
                ->get();
        }

        $query = FinanceJournal::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $scope->id)
            ->with(['period', 'unit', 'lines.account', 'lines.costCenter', 'reversal'])
            ->withCount('lines')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        if (trim($this->search) !== '') {
            $search = trim($this->search);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('journal_number', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        if ($this->filterPeriodId) {
            $query->where('finance_period_id', $this->filterPeriodId);
        }

        if ($this->filterUnitId) {
            $query->where('unit_id', $this->filterUnitId);
        }

        if ($this->fromDate !== '') {
            $query->whereDate('transaction_date', '>=', $this->fromDate);
        }

        if ($this->toDate !== '') {
            $query->whereDate('transaction_date', '<=', $this->toDate);
        }

        $journalEntries = $query->paginate(15);

        return view('finance::livewire.journals.manage', [
            'accounts' => $accounts,
            'openPeriods' => $openPeriods,
            'allPeriods' => $allPeriods,
            'units' => $units,
            'committees' => $committees,
            'hasCommitteeTable' => $hasCommitteeTable,
            'journalEntries' => $journalEntries,
            'draftDebitTotal' => collect($this->lines)->sum(fn (array $line): float => (float) ($line['debit'] ?? 0)),
            'draftCreditTotal' => collect($this->lines)->sum(fn (array $line): float => (float) ($line['credit'] ?? 0)),
            'canPost' => $this->can($authorization, 'finance.journal.post'),
            'canReverse' => $this->can($authorization, 'finance.journal.reverse'),
        ])->layout('core::layouts.organization-admin', [
            'pageTitle' => 'Journal Entries',
            'layoutOrganization' => $this->organization,
        ]);
    }

    private function emptyLine(): array
    {
        return [
            'finance_account_id' => '',
            'cost_center_type' => 'none',
            'cost_center_id' => null,
            'description' => '',
            'debit' => '0.0000',
            'credit' => '0.0000',
        ];
    }

    private function ensureCanView(AuthorizationService $authorization): void
    {
        abort_unless(
            $this->can($authorization, 'finance.journal.view')
                || $this->can($authorization, 'finance.journal.post')
                || $this->can($authorization, 'finance.journal.reverse'),
            403,
        );
    }

    private function ensureCanPost(AuthorizationService $authorization): void
    {
        abort_unless($this->can($authorization, 'finance.journal.post'), 403);
    }

    private function ensureCanReverse(AuthorizationService $authorization): void
    {
        abort_unless($this->can($authorization, 'finance.journal.reverse'), 403);
    }

    private function can(AuthorizationService $authorization, string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && $this->organization->exists && $authorization->can(
            $user,
            $permission,
            new AuthorizationContext((int) $this->organization->id),
        );
    }
}
