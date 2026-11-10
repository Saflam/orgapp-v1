<?php

namespace Modules\Finance\Livewire;

use App\Support\Organization\CurrentOrganization;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationService;
use Modules\Finance\Models\FinanceJournal;
use Modules\Finance\Services\FinanceScopeService;

class FinanceJournalDetail extends Component
{
    public Organization $organization;

    public int $journalId;

    public function mount(
        int $journalId,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        FinanceScopeService $scopes,
    ): void {
        $this->organization = $currentOrganization->get();

        abort_unless(
            $this->organization instanceof Organization && $this->organization->exists,
            404,
        );

        $this->ensureCanView($authorization);

        $scope = $scopes->organizationScope($this->organization);

        FinanceJournal::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $scope->id)
            ->findOrFail($journalId);

        $this->journalId = $journalId;
    }

    public function render(
        AuthorizationService $authorization,
        FinanceScopeService $scopes,
    ): View
    {
        $this->ensureCanView($authorization);

        $scope = $scopes->organizationScope($this->organization);
        $journal = FinanceJournal::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $scope->id)
            ->with(['period', 'unit', 'lines.account', 'originalJournal', 'reversal'])
            ->findOrFail($this->journalId);

        return view('finance::livewire.journals.detail', [
            'journal' => $journal,
            'debitTotal' => $journal->lines->sum(fn ($line): float => (float) $line->debit),
            'creditTotal' => $journal->lines->sum(fn ($line): float => (float) $line->credit),
        ])->layout('core::layouts.organization-admin', [
            'pageTitle' => 'Journal ' . $journal->journal_number,
            'layoutOrganization' => $this->organization,
        ]);
    }

    private function ensureCanView(AuthorizationService $authorization): void
    {
        $user = auth()->user();
        $context = new AuthorizationContext((int) $this->organization->id);

        abort_unless(
            $user !== null && (
                $authorization->can($user, 'finance.journal.view', $context)
                || $authorization->can($user, 'finance.journal.post', $context)
                || $authorization->can($user, 'finance.journal.reverse', $context)
            ),
            403,
        );
    }
}
