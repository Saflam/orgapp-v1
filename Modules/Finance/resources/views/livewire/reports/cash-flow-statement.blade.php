<div class="finance-cash-flow">
    <header class="finance-cash-flow__header">
        <div>
            <p class="finance-cash-flow__eyebrow">FINANCE REPORTS</p>
            <h1>Cash Flow Statement</h1>
            <p class="finance-cash-flow__description">
                Direct-method summary of cash generated and used by operating, investing and financing activities.
            </p>
        </div>
    </header>

    <section class="finance-cash-flow__filter-card" aria-label="Report filters">
        <div class="finance-cash-flow__filter-grid">
            <div class="finance-cash-flow__field">
                <label for="cashFlowStartDate">Start date</label>
                <input id="cashFlowStartDate" type="date" wire:model.live="startDate">
                @error('startDate')
                    <small class="finance-cash-flow__error">{{ $message }}</small>
                @enderror
            </div>

            <div class="finance-cash-flow__field">
                <label for="cashFlowEndDate">End date</label>
                <input id="cashFlowEndDate" type="date" wire:model.live="endDate">
                @error('endDate')
                    <small class="finance-cash-flow__error">{{ $message }}</small>
                @enderror
            </div>

            <div class="finance-cash-flow__field">
                <label for="cashFlowUnitId">Unit</label>
                <select id="cashFlowUnitId" wire:model.live="unitId">
                    <option value="">All Units</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </section>

    <section class="finance-cash-flow__report-card" aria-label="Cash flow report">
        <div class="finance-cash-flow__report-header">
            <div>
                <h2>Cash flow summary</h2>
                <p>
                    {{ \Illuminate\Support\Carbon::parse($report['start_date'])->format('d M Y') }}
                    – {{ \Illuminate\Support\Carbon::parse($report['end_date'])->format('d M Y') }}
                </p>
            </div>
            <span class="finance-cash-flow__report-tag">DIRECT METHOD</span>
        </div>

        <div class="finance-cash-flow__opening">
            <span>Opening cash</span>
            <strong>{{ $report['opening_cash_balance'] }}</strong>
        </div>

        <div class="finance-cash-flow__activities">
            <section class="finance-cash-flow__activity">
                <div>
                    <h3>Operating activities</h3>
                    <p>Cash generated from or used in the organization's operating activities.</p>
                </div>
                <strong>{{ $report['operating_net_cash_flow'] }}</strong>
            </section>

            <section class="finance-cash-flow__activity">
                <div>
                    <h3>Investing activities</h3>
                    <p>Cash generated from or used for investments and long-term assets.</p>
                </div>
                <strong>{{ $report['investing_net_cash_flow'] }}</strong>
            </section>

            <section class="finance-cash-flow__activity">
                <div>
                    <h3>Financing activities</h3>
                    <p>Cash generated from or used for financing arrangements.</p>
                </div>
                <strong>{{ $report['financing_net_cash_flow'] }}</strong>
            </section>
        </div>

        <div class="finance-cash-flow__totals">
            <div class="finance-cash-flow__total-row">
                <span>Net change in cash</span>
                <strong>{{ $report['net_change_in_cash'] }}</strong>
            </div>
            <div class="finance-cash-flow__total-row finance-cash-flow__closing">
                <span>Closing cash</span>
                <strong>{{ $report['closing_cash_balance'] }}</strong>
            </div>
        </div>
    </section>

    <p class="finance-cash-flow__note">
        Only posted journals affecting configured cash or bank accounts are included.
        Cash-to-cash transfers are excluded from activity totals. All amounts are shown to four decimal places.
    </p>
</div>

<style>
    .finance-cash-flow {
        --fcf-line: var(--line, #e3e8f0);
        --fcf-panel: var(--panel, #fff);
        --fcf-canvas: var(--canvas, #f6f8fc);
        --fcf-ink: var(--ink, #172033);
        --fcf-muted: var(--muted, #68758b);
        --fcf-brand: var(--brand, #4776e6);
        display: grid;
        gap: 20px;
        min-width: 0;
        color: var(--fcf-ink);
    }
    .finance-cash-flow__header h1 { margin: 0; }
    .finance-cash-flow__eyebrow {
        margin: 0 0 6px; color: var(--fcf-brand); font-size: 10px;
        font-weight: 800; letter-spacing: .1em;
    }
    .finance-cash-flow__description { margin: 7px 0 0; color: var(--fcf-muted); font-size: 13px; }
    .finance-cash-flow__filter-card,
    .finance-cash-flow__report-card {
        min-width: 0; overflow: hidden; border: 1px solid var(--fcf-line);
        border-radius: 14px; background: var(--fcf-panel); box-shadow: 0 4px 18px rgb(22 37 66 / 3%);
    }
    .finance-cash-flow__filter-card { padding: 20px; }
    .finance-cash-flow__filter-grid {
        display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px;
    }
    .finance-cash-flow__field { display: flex; min-width: 0; flex-direction: column; gap: 7px; }
    .finance-cash-flow__field label { color: var(--fcf-ink); font-size: 12px; font-weight: 750; }
    .finance-cash-flow__field input,
    .finance-cash-flow__field select {
        width: 100%; min-width: 0; min-height: 42px; padding: 9px 12px;
        border: 1px solid var(--fcf-line); border-radius: 9px; background: var(--fcf-panel);
        color: var(--fcf-ink); font: inherit; font-size: 13px;
    }
    .finance-cash-flow__error { color: #b42318; font-size: 12px; }
    .finance-cash-flow__report-header {
        display: flex; align-items: center; justify-content: space-between; gap: 16px;
        padding: 20px 22px; border-bottom: 1px solid var(--fcf-line);
    }
    .finance-cash-flow__report-header h2 { margin: 0; color: var(--fcf-ink); font-size: 17px; font-weight: 750; }
    .finance-cash-flow__report-header p { margin: 5px 0 0; color: var(--fcf-muted); font-size: 12px; }
    .finance-cash-flow__report-tag {
        padding: 5px 9px; border: 1px solid var(--fcf-line); border-radius: 999px;
        background: var(--fcf-canvas); color: var(--fcf-muted); font-size: 9px;
        font-weight: 800; letter-spacing: .08em; white-space: nowrap;
    }
    .finance-cash-flow__opening,
    .finance-cash-flow__total-row {
        display: flex; align-items: center; justify-content: space-between; gap: 16px;
        padding: 17px 22px;
    }
    .finance-cash-flow__opening { background: var(--fcf-canvas); border-bottom: 1px solid var(--fcf-line); }
    .finance-cash-flow__opening span,
    .finance-cash-flow__total-row span { color: var(--fcf-muted); font-size: 13px; font-weight: 650; }
    .finance-cash-flow__opening strong,
    .finance-cash-flow__total-row strong { color: var(--fcf-ink); font-size: 14px; font-variant-numeric: tabular-nums; }
    .finance-cash-flow__activities { padding: 4px 22px; }
    .finance-cash-flow__activity {
        display: flex; align-items: center; justify-content: space-between; gap: 18px;
        padding: 19px 0; border-bottom: 1px solid var(--fcf-line);
    }
    .finance-cash-flow__activity h3 { margin: 0; color: var(--fcf-ink); font-size: 14px; font-weight: 800; }
    .finance-cash-flow__activity p { margin: 5px 0 0; color: var(--fcf-muted); font-size: 12px; }
    .finance-cash-flow__activity > strong {
        color: var(--fcf-ink); font-size: 14px; font-variant-numeric: tabular-nums; white-space: nowrap;
    }
    .finance-cash-flow__totals { border-top: 1px solid var(--fcf-line); }
    .finance-cash-flow__total-row { border-bottom: 1px solid var(--fcf-line); }
    .finance-cash-flow__closing { background: var(--fcf-canvas); }
    .finance-cash-flow__closing span,
    .finance-cash-flow__closing strong { color: var(--fcf-ink); font-size: 15px; font-weight: 800; }
    .finance-cash-flow__note { margin: 0; color: var(--fcf-muted); font-size: 11px; line-height: 1.6; }
    @media (max-width: 760px) {
        .finance-cash-flow__filter-grid { grid-template-columns: 1fr; }
        .finance-cash-flow__report-header { align-items: flex-start; }
        .finance-cash-flow__activity { align-items: flex-start; }
    }
</style>
