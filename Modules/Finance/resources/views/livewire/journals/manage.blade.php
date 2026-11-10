<div class="finance-journal-management">
    <div class="page-head">
        <div>
            <h1>Journal Entries</h1>
            <p class="muted">Post manual double-entry journals to the organization-wide ledger. Posted journals are permanent accounting records.</p>
        </div>
    </div>

    @if ($successMessage !== '')
        <div class="notice" role="status">{{ $successMessage }}</div>
    @endif
    @error('journal') <div class="error" role="alert">{{ $message }}</div> @enderror
    @error('lines') <div class="error" role="alert">{{ $message }}</div> @enderror

    @if ($canPost)
        <section class="card">
            <h2>Post a journal</h2>
            <p class="muted">Enter at least two lines. Each line must be either a debit or a credit, and total debits must equal total credits.</p>

            <form wire:submit="postJournal">
                <div class="grid journal-entry-fields">
                    <div class="field">
                        <label for="journal-date">Transaction date</label>
                        <input id="journal-date" type="date" wire:model="transactionDate" required>
                        @error('transactionDate') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="journal-period">Financial period</label>
                        <select id="journal-period" wire:model="periodId" required>
                            <option value="">Select an open period</option>
                            @foreach ($openPeriods as $period)
                                <option value="{{ $period->id }}">{{ $period->code }} — {{ $period->starts_on->format('d M Y') }} to {{ $period->ends_on->format('d M Y') }}</option>
                            @endforeach
                        </select>
                        @error('periodId') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="journal-unit">Unit attribution (optional)</label>
                        <select id="journal-unit" wire:model="unitId">
                            <option value="">Organization-wide</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                            @endforeach
                        </select>
                        @error('unitId') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="journal-description">Journal description</label>
                        <input id="journal-description" type="text" wire:model="description" maxlength="1000" placeholder="Reason for this journal">
                        @error('description') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <h3>Journal lines</h3>
                <div class="table-wrap journal-entry-lines">
                    <table>
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th>Description</th>
                                <th>Debit</th>
                                <th>Credit</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lines as $index => $line)
                                <tr wire:key="journal-line-{{ $index }}">
                                    <td>
                                        <select wire:model="lines.{{ $index }}.finance_account_id" required>
                                            <option value="">Select account</option>
                                            @foreach ($accounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                            @endforeach
                                        </select>
                                        @error("lines.$index.finance_account_id") <span class="error">{{ $message }}</span> @enderror
                                    </td>
                                    <td>
                                        <input type="text" wire:model="lines.{{ $index }}.description" maxlength="500" placeholder="Optional line description">
                                        @error("lines.$index.description") <span class="error">{{ $message }}</span> @enderror
                                    </td>
                                    <td>
                                        <input type="number" min="0" step="0.0001" wire:model.live="lines.{{ $index }}.debit" required>
                                        @error("lines.$index.debit") <span class="error">{{ $message }}</span> @enderror
                                    </td>
                                    <td>
                                        <input type="number" min="0" step="0.0001" wire:model.live="lines.{{ $index }}.credit" required>
                                        @error("lines.$index.credit") <span class="error">{{ $message }}</span> @enderror
                                    </td>
                                    <td>
                                        <button class="btn danger" type="button" wire:click="removeLine({{ $index }})" @disabled(count($lines) <= 2)>Remove</button>
                                    </td>
                                </tr>
                            @endforeach
                            <tr>
                                <th colspan="2">Totals</th>
                                <th>{{ number_format($draftDebitTotal, 4) }}</th>
                                <th>{{ number_format($draftCreditTotal, 4) }}</th>
                                <th></th>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="actions">
                    <button class="btn secondary" type="button" wire:click="addLine" @disabled(count($lines) >= 50)>Add line</button>
                    <button class="btn" type="submit" wire:loading.attr="disabled" wire:target="postJournal">
                        <span wire:loading.remove wire:target="postJournal">Post Journal</span>
                        <span wire:loading wire:target="postJournal">Posting…</span>
                    </button>
                    <button class="btn secondary" type="button" wire:click="resetEntryForm">Reset</button>
                </div>
            </form>
        </section>
    @endif

    <section class="card">
        <h2>Journal register</h2>
        <div class="grid journal-register-filters">
            <div class="field">
                <label for="journal-search">Search</label>
                <input id="journal-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Journal number or description">
            </div>
            <div class="field">
                <label for="journal-filter-period">Financial period</label>
                <select id="journal-filter-period" wire:model.live="filterPeriodId">
                    <option value="">All periods</option>
                    @foreach ($allPeriods as $period)
                        <option value="{{ $period->id }}">{{ $period->code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="journal-filter-unit">Unit</label>
                <select id="journal-filter-unit" wire:model.live="filterUnitId">
                    <option value="">All units</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="journal-from-date">From date</label>
                <input id="journal-from-date" type="date" wire:model.live="fromDate">
            </div>
            <div class="field">
                <label for="journal-to-date">To date</label>
                <input id="journal-to-date" type="date" wire:model.live="toDate">
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Journal number</th>
                        <th>Date</th>
                        <th>Period</th>
                        <th>Unit</th>
                        <th>Description</th>
                        <th>Lines</th>
                        <th>Total debit</th>
                        <th>Total credit</th>
                        <th>Status</th>
                        @if ($canReverse)<th>Action</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($journalEntries as $journal)
                        <tr wire:key="finance-journal-{{ $journal->id }}">
                            <td>
                                {{ $journal->journal_number }}
                                @if ($journal->isReversal())
                                    <div class="muted">Reversal of {{ $journal->originalJournal?->journal_number ?? 'journal' }}</div>
                                @elseif ($journal->reversal)
                                    <div class="muted">Reversed by {{ $journal->reversal->journal_number }}</div>
                                @endif
                            </td>
                            <td>{{ $journal->transaction_date->format('d M Y') }}</td>
                            <td>{{ $journal->period?->code ?? '—' }}</td>
                            <td>{{ $journal->unit?->name ?? 'Organization' }}</td>
                            <td>{{ $journal->description ?: '—' }}</td>
                            <td>{{ $journal->lines_count }}</td>
                            <td>{{ number_format((float) $journal->lines->sum('debit'), 4) }}</td>
                            <td>{{ number_format((float) $journal->lines->sum('credit'), 4) }}</td>
                            <td><span class="pill ok">{{ ucfirst($journal->status->value) }}</span></td>
                            @if ($canReverse)
                                <td>
                                    @if ($journal->isPosted() && ! $journal->isReversal() && ! $journal->reversal)
                                        <button class="btn secondary" type="button" wire:click="prepareReversal({{ $journal->id }})">Reverse</button>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canReverse ? 10 : 9 }}" class="empty">No journal entries match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="muted">Showing up to 100 matching journal entries, newest first.</p>
    </section>

    @if ($reversalJournalId)
        <section class="card" aria-labelledby="reverse-journal-heading">
            <h2 id="reverse-journal-heading">Reverse journal</h2>
            <p class="muted">A new posted journal will be created with the original debit and credit amounts swapped. The original journal will remain unchanged.</p>
            <form wire:submit="reverseJournal">
                <div class="grid">
                    <div class="field">
                        <label for="reversal-date">Reversal date</label>
                        <input id="reversal-date" type="date" wire:model="reversalDate" required>
                        @error('reversalDate') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="reversal-period">Open financial period</label>
                        <select id="reversal-period" wire:model="reversalPeriodId" required>
                            <option value="">Select an open period</option>
                            @foreach ($openPeriods as $period)
                                <option value="{{ $period->id }}">{{ $period->code }} — {{ $period->starts_on->format('d M Y') }} to {{ $period->ends_on->format('d M Y') }}</option>
                            @endforeach
                        </select>
                        @error('reversalPeriodId') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="reversal-reason">Reason for reversal</label>
                        <textarea id="reversal-reason" wire:model="reversalReason" rows="3" maxlength="1000" required></textarea>
                        @error('reversalReason') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="actions">
                    <button class="btn" type="submit" wire:loading.attr="disabled" wire:target="reverseJournal">Confirm reversal</button>
                    <button class="btn secondary" type="button" wire:click="cancelReversal">Cancel</button>
                </div>
            </form>
        </section>
    @endif
</div>


<style>
    .finance-journal-management .journal-entry-fields,
    .finance-journal-management .journal-register-filters {
        display: grid;
        gap: 12px 16px;
    }

    .finance-journal-management .journal-entry-fields {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .finance-journal-management .journal-register-filters {
        grid-template-columns: minmax(260px, 2.2fr) repeat(4, minmax(130px, 1fr));
        align-items: end;
    }

    .finance-journal-management .card {
        padding: 16px 20px;
    }

    .finance-journal-management .card h2 {
        margin-top: 0;
        margin-bottom: 8px;
    }

    .finance-journal-management .card > .muted {
        margin-top: 0;
        margin-bottom: 10px;
    }

    .finance-journal-management .field {
        margin-bottom: 0;
    }

    .finance-journal-management .field label {
        margin-bottom: 5px;
    }

    .finance-journal-management input,
    .finance-journal-management select,
    .finance-journal-management textarea {
        min-height: 38px;
        padding-top: 7px;
        padding-bottom: 7px;
    }

    .finance-journal-management .journal-entry-lines {
        overflow-x: auto;
    }

    .finance-journal-management .journal-entry-lines table {
        width: 100%;
        min-width: 850px;
        table-layout: fixed;
    }

    .finance-journal-management .journal-entry-lines th,
    .finance-journal-management .journal-entry-lines td {
        padding: 7px 9px;
        vertical-align: middle;
    }

    .finance-journal-management .journal-entry-lines th:nth-child(1) { width: 25%; }
    .finance-journal-management .journal-entry-lines th:nth-child(2) { width: 21%; }
    .finance-journal-management .journal-entry-lines th:nth-child(3),
    .finance-journal-management .journal-entry-lines th:nth-child(4) { width: 19%; }
    .finance-journal-management .journal-entry-lines th:nth-child(5) { width: 16%; }

    .finance-journal-management .journal-entry-lines input,
    .finance-journal-management .journal-entry-lines select {
        width: 100%;
        min-width: 0;
    }

    .finance-journal-management .journal-entry-lines .btn,
    .finance-journal-management .actions .btn {
        min-height: 36px;
        padding: 7px 12px;
    }

    .finance-journal-management h3 {
        margin-top: 12px;
        margin-bottom: 6px;
    }

    .finance-journal-management .actions {
        margin-top: 10px;
        gap: 8px;
    }

    @media (max-width: 1100px) {
        .finance-journal-management .journal-register-filters {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .finance-journal-management .journal-register-filters .field:first-child {
            grid-column: span 2;
        }
    }

    @media (max-width: 700px) {
        .finance-journal-management .card {
            padding: 14px;
        }

        .finance-journal-management .journal-entry-fields,
        .finance-journal-management .journal-register-filters {
            grid-template-columns: minmax(0, 1fr);
        }

        .finance-journal-management .journal-register-filters .field:first-child {
            grid-column: auto;
        }
    }
</style>
