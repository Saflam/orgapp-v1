<div class="finance-balance-sheet">
    <header class="finance-balance-sheet__header">
        <div>
            <p class="finance-balance-sheet__eyebrow">FINANCE REPORTS</p>
            <h1>Balance Sheet</h1>
            <p class="finance-balance-sheet__description">
                Cumulative assets, liabilities and equity from posted transactions through the selected date.
            </p>
        </div>
    </header>

    <section class="finance-balance-sheet__filter-card" aria-label="Report filters">
        <div class="finance-balance-sheet__filter-grid">
            <div class="finance-balance-sheet__field">
                <label for="asOfDate">As of date</label>
                <input id="asOfDate" type="date" wire:model.live="asOfDate">
                @error('asOfDate')
                    <small class="finance-balance-sheet__error">{{ $message }}</small>
                @enderror
            </div>

            <div class="finance-balance-sheet__field">
                <label for="costCenterType">Cost centre scope</label>
                <select id="costCenterType" wire:model.live="costCenterType">
                    <option value="all">All Cost Centres (All Units)</option>
                    <option value="organization">Central / Organization</option>
                    <option value="unit">Unit</option>
                    @if ($hasCommitteeTable)
                        <option value="committee">Committee</option>
                    @endif
                </select>
            </div>

            @if (in_array($costCenterType, ['unit', 'committee'], true))
                <div class="finance-balance-sheet__field">
                    <label for="costCenterOwnerId">{{ $costCenterType === 'unit' ? 'Unit' : 'Committee' }}</label>
                    <select id="costCenterOwnerId" wire:model.live="costCenterOwnerId">
                        <option value="">Select {{ $costCenterType === 'unit' ? 'unit' : 'committee' }}</option>
                        @if ($costCenterType === 'unit')
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                            @endforeach
                        @else
                            @foreach ($committees as $committee)
                                <option value="{{ $committee->id }}">{{ $committee->name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>
            @endif
        </div>
    </section>

    @if ($filterRequired)
        <div class="finance-balance-sheet__filter-card" role="status">
            Select a {{ $costCenterType === 'unit' ? 'unit' : 'committee' }} to generate this report.
        </div>
    @else
    <section class="finance-balance-sheet__report-card">
        <div class="finance-balance-sheet__report-header">
            <div>
                <h2>Financial position</h2>
                <p>As of {{ \Illuminate\Support\Carbon::parse($report['as_of_date'])->format('d M Y') }}</p>
            </div>
            <div class="finance-balance-sheet__report-actions">
                <span class="finance-balance-sheet__report-tag">CUMULATIVE</span>
                <a
                    class="finance-balance-sheet__export-button"
                    href="{{ route('finance.reports.balance-sheet.pdf', array_filter([
                        'as_of_date' => $report['as_of_date'],
                        'cost_center_type' => $costCenterType,
                        'cost_center_owner_id' => $costCenterOwnerId,
                        'unit_id' => $costCenterType === 'unit' ? $costCenterOwnerId : null,
                    ], fn ($value) => $value !== null && $value !== '')) }}"
                    target="_blank"
                    rel="noopener"
                >
                    <span aria-hidden="true">↓</span>
                    Export PDF
                </a>
            </div>
        </div>

        <div class="finance-balance-sheet__sections">
            <section class="finance-balance-sheet__section" aria-labelledby="balance-assets-heading">
                <h3 id="balance-assets-heading">Assets</h3>
                @include('finance::livewire.reports.partials.balance-sheet-account-table', [
                    'accounts' => $report['asset_accounts'],
                    'emptyMessage' => 'No asset balances for this date.',
                ])
                <div class="finance-balance-sheet__total-row">
                    <span>Total assets</span>
                    <strong>{{ $report['total_assets'] }}</strong>
                </div>
            </section>

            <section class="finance-balance-sheet__section" aria-labelledby="balance-liabilities-heading">
                <h3 id="balance-liabilities-heading">Liabilities</h3>
                @include('finance::livewire.reports.partials.balance-sheet-account-table', [
                    'accounts' => $report['liability_accounts'],
                    'emptyMessage' => 'No liability balances for this date.',
                ])
                <div class="finance-balance-sheet__total-row">
                    <span>Total liabilities</span>
                    <strong>{{ $report['total_liabilities'] }}</strong>
                </div>
            </section>

            <section class="finance-balance-sheet__section" aria-labelledby="balance-equity-heading">
                <h3 id="balance-equity-heading">Equity</h3>
                @include('finance::livewire.reports.partials.balance-sheet-account-table', [
                    'accounts' => $report['equity_accounts'],
                    'emptyMessage' => 'No equity balances for this date.',
                ])
                <div class="finance-balance-sheet__total-row">
                    <span>Total equity</span>
                    <strong>{{ $report['total_equity'] }}</strong>
                </div>
                <div class="finance-balance-sheet__earnings-row">
                    <span>Current earnings</span>
                    <strong>{{ $report['current_earnings']['amount'] }}</strong>
                </div>
            </section>
        </div>

        <div class="finance-balance-sheet__summary">
            <div class="finance-balance-sheet__summary-row">
                <span>Total liabilities and equity</span>
                <strong>{{ $report['total_liabilities_and_equity'] }}</strong>
            </div>
            <div class="finance-balance-sheet__summary-row finance-balance-sheet__difference-row">
                <span>Difference</span>
                <strong>{{ $report['difference'] }}</strong>
            </div>
            <p class="finance-balance-sheet__balance-status {{ $report['difference'] === '0.0000' ? 'is-balanced' : 'has-difference' }}">
                @if ($report['difference'] === '0.0000')
                    Balanced — assets equal liabilities and equity.
                @else
                    Out of balance — review the account balances.
                @endif
            </p>
        </div>
    </section>
    @endif
</div>

<style>
    .finance-balance-sheet {
        --fbs-line: var(--line, #e3e8f0);
        --fbs-panel: var(--panel, #fff);
        --fbs-canvas: var(--canvas, #f6f8fc);
        --fbs-ink: var(--ink, #172033);
        --fbs-muted: var(--muted, #68758b);
        --fbs-brand: var(--brand, #4776e6);
        display: grid;
        gap: 20px;
        min-width: 0;
        color: var(--fbs-ink);
    }
    .finance-balance-sheet__header h1 { margin: 0; }
    .finance-balance-sheet__eyebrow {
        margin: 0 0 6px;
        color: var(--fbs-brand);
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .1em;
    }
    .finance-balance-sheet__description { margin: 7px 0 0; color: var(--fbs-muted); font-size: 13px; }
    .finance-balance-sheet__filter-card,
    .finance-balance-sheet__report-card {
        min-width: 0; overflow: hidden; border: 1px solid var(--fbs-line);
        border-radius: 14px; background: var(--fbs-panel); box-shadow: 0 4px 18px rgb(22 37 66 / 3%);
    }
    .finance-balance-sheet__filter-card { padding: 20px; }
    .finance-balance-sheet__filter-grid {
        display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; max-width: 760px;
    }
    .finance-balance-sheet__field { display: flex; min-width: 0; flex-direction: column; gap: 7px; }
    .finance-balance-sheet__field label { color: var(--fbs-ink); font-size: 12px; font-weight: 750; }
    .finance-balance-sheet__field input,
    .finance-balance-sheet__field select {
        width: 100%; min-width: 0; min-height: 42px; padding: 9px 12px;
        border: 1px solid var(--fbs-line); border-radius: 9px; background: var(--fbs-panel);
        color: var(--fbs-ink); font: inherit; font-size: 13px;
    }
    .finance-balance-sheet__error { color: #b42318; font-size: 12px; }
    .finance-balance-sheet__report-header {
        display: flex; align-items: center; justify-content: space-between; gap: 16px;
        padding: 20px 22px; border-bottom: 1px solid var(--fbs-line);
    }
    .finance-balance-sheet__report-header h2 { margin: 0; color: var(--fbs-ink); font-size: 17px; font-weight: 750; }
    .finance-balance-sheet__report-header p { margin: 5px 0 0; color: var(--fbs-muted); font-size: 12px; }
    .finance-balance-sheet__report-actions { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 9px; }
    .finance-balance-sheet__report-tag {
        padding: 5px 9px; border: 1px solid var(--fbs-line); border-radius: 999px;
        background: var(--fbs-canvas); color: var(--fbs-muted); font-size: 9px; font-weight: 800; letter-spacing: .08em;
    }
    .finance-balance-sheet__export-button {
        display: inline-flex; min-height: 34px; align-items: center; justify-content: center; gap: 7px;
        padding: 7px 12px; border: 1px solid var(--fbs-brand); border-radius: 8px;
        background: var(--fbs-brand); color: #fff; font-size: 12px; font-weight: 750; text-decoration: none; white-space: nowrap;
    }
    .finance-balance-sheet__export-button:hover { filter: brightness(.95); color: #fff; }
    .finance-balance-sheet__sections {
        display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 24px; padding: 4px 22px 20px;
    }
    .finance-balance-sheet__section { min-width: 0; padding: 18px 0; border-bottom: 1px solid var(--fbs-line); }
    .finance-balance-sheet__section h3 { margin: 0 0 12px; color: var(--fbs-ink); font-size: 14px; font-weight: 800; }
    .finance-balance-sheet__table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .finance-balance-sheet__table th, .finance-balance-sheet__table td { padding: 8px 5px; border-bottom: 1px solid var(--fbs-line); text-align: left; }
    .finance-balance-sheet__table th { color: var(--fbs-muted); font-size: 10px; font-weight: 750; text-transform: uppercase; letter-spacing: .04em; }
    .finance-balance-sheet__table td { color: var(--fbs-ink); }
    .finance-balance-sheet__table .is-numeric { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .finance-balance-sheet__empty { padding: 12px 5px !important; color: var(--fbs-muted) !important; }
    .finance-balance-sheet__total-row,
    .finance-balance-sheet__earnings-row,
    .finance-balance-sheet__summary-row {
        display: flex; justify-content: space-between; gap: 12px; padding: 11px 5px 0; color: var(--fbs-ink); font-size: 12px;
    }
    .finance-balance-sheet__total-row strong,
    .finance-balance-sheet__earnings-row strong,
    .finance-balance-sheet__summary-row strong { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .finance-balance-sheet__total-row { font-weight: 800; }
    .finance-balance-sheet__earnings-row { margin-top: 8px; padding-top: 11px; border-top: 1px dashed var(--fbs-line); color: var(--fbs-muted); }
    .finance-balance-sheet__summary { padding: 18px 22px 20px; border-top: 1px solid var(--fbs-line); background: var(--fbs-canvas); }
    .finance-balance-sheet__summary-row { padding: 7px 0; font-size: 13px; font-weight: 800; }
    .finance-balance-sheet__difference-row { margin-top: 5px; padding-top: 12px; border-top: 1px solid var(--fbs-line); }
    .finance-balance-sheet__balance-status { margin: 9px 0 0; font-size: 11px; font-weight: 700; }
    .finance-balance-sheet__balance-status.is-balanced { color: #18794e; }
    .finance-balance-sheet__balance-status.has-difference { color: #b42318; }
    @media (max-width: 760px) {
        .finance-balance-sheet__filter-grid, .finance-balance-sheet__sections { grid-template-columns: 1fr; }
        .finance-balance-sheet__report-header { align-items: flex-start; flex-direction: column; }
        .finance-balance-sheet__report-actions { justify-content: flex-start; }
    }
</style>
