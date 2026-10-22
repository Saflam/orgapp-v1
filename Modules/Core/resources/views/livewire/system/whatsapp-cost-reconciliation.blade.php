<div>
    <div class="page-head">
        <div>
            <h1>WhatsApp cost reporting &amp; reconciliation</h1>
            <div class="muted">Compare provider-reported message charges with the Core WhatsApp usage ledger.</div>
        </div>
        <a class="btn secondary" href="/system/whatsapp-usage-records">Back to usage records</a>
    </div>
    <div class="long-page-layout">
        @include('core::livewire.system.partials.whatsapp-management-nav', ['active' => 'costs'])
        <div class="long-page-content">
            <section class="card">
                <div class="page-head"><div><h2>Import provider cost CSV</h2><p class="muted">Retry unmatched rows after internal WhatsApp usage records arrive.</p></div><button class="btn secondary" type="button" wire:click="reconcilePending" wire:loading.attr="disabled" wire:target="reconcilePending">Retry unmatched rows</button></div>
                <p class="muted">Upload the normalized CSV format shown below. Provider exports may need to be transformed to these column names before import. Costs are taken from this file; the webhook's billable flag alone is not a monetary charge.</p>
                @if ($importMessage !== '') <div class="alert success" role="status">{{ $importMessage }}</div> @endif
                @if ($importError !== '') <div class="alert error" role="alert">{{ $importError }}</div> @endif
                <form wire:submit="import" class="stack">
                    <div class="field">
                        <label for="wa-cost-csv">CSV file (maximum 5 MB)</label>
                        <input id="wa-cost-csv" type="file" wire:model="csvFile" accept=".csv,.txt">
                        @error('csvFile') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <button class="btn" type="submit" wire:loading.attr="disabled" wire:target="import,csvFile">Import and reconcile</button>
                    <div wire:loading wire:target="import" class="muted">Importing and matching message IDs…</div>
                </form>
                <details style="margin-top:16px">
                    <summary>Required CSV columns and example</summary>
                    <p class="muted">Required headers: <code>provider_message_id,cost_amount,currency,pricing_category,billable,occurred_at</code>. Date/time values should be parseable by PHP, for example ISO 8601.</p>
                    <pre style="white-space:pre-wrap;overflow-wrap:anywhere">provider_message_id,cost_amount,currency,pricing_category,billable,occurred_at
wamid.example.001,0.012500,USD,utility,true,2026-10-17T10:30:00Z</pre>
                </details>
            </section>

            <section class="card">
                <h2>Provider-reported costs</h2>
                <div class="grid">
                    <div class="field">
                        <label for="wa-cost-organization">Organization</label>
                        <select id="wa-cost-organization" wire:model.live="organizationId">
                            <option value="">All organizations</option>
                            @foreach ($organizations as $organization)
                                <option value="{{ $organization->id }}">{{ $organization->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="date-filter-row" style="display:grid;grid-template-columns:repeat(2,minmax(0,160px));gap:12px;max-width:332px;align-items:end">
                        <div class="field" style="min-width:0"><label for="wa-cost-from">From</label><input id="wa-cost-from" type="date" wire:model.live="fromDate" style="width:100%;min-width:0"></div>
                        <div class="field" style="min-width:0"><label for="wa-cost-to">To</label><input id="wa-cost-to" type="date" wire:model.live="toDate" style="width:100%;min-width:0"></div>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Currency</th><th>Matched messages</th><th>Provider-reported total</th></tr></thead>
                        <tbody>
                        @forelse ($costTotals as $total)
                            <tr><td>{{ $total->currency ?? 'Unknown' }}</td><td>{{ number_format($total->matched_messages) }}</td><td>{{ number_format((float) $total->total_cost, 6) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="empty">No matched provider costs for these filters.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="muted">Totals are grouped by currency. Different currencies are never added together.</p>
                <div class="grid">
                    <div><strong>{{ number_format((int) ($issueCounts['unmatched'] ?? 0)) }}</strong><div class="muted">Unmatched provider rows</div></div>
                    <div><strong>{{ number_format((int) ($issueCounts['duplicate'] ?? 0)) }}</strong><div class="muted">Duplicate rows excluded from totals</div></div>
                    <div><strong>{{ number_format((int) ($issueCounts['invalid'] ?? 0)) }}</strong><div class="muted">Invalid rows</div></div>
                    <div><strong>{{ number_format((int) ($issueCounts['discrepancy'] ?? 0)) }}</strong><div class="muted">Matched rows with discrepancies</div></div>
                </div>
            </section>

            <section class="card">
                <div class="page-head"><div><h2>Reconciliation details</h2><p class="muted">Export the rows matching the current organization and date filters, including unresolved issues.</p></div><button class="btn secondary" type="button" wire:click="exportCsv" wire:loading.attr="disabled" wire:target="exportCsv">Export filtered CSV</button></div>
                <div class="table-wrap" style="width:100%;max-width:100%;min-width:0;overflow-x:auto;overscroll-behavior-x:contain;-webkit-overflow-scrolling:touch">
                    <table style="min-width:1200px;width:max-content">
                        <thead><tr><th>Provider date</th><th>Organization</th><th>Provider message ID</th><th>Cost</th><th>Currency</th><th>Category</th><th>Status</th><th>Internal cost</th><th>Internal currency</th><th>Discrepancies</th><th>Issue</th></tr></thead>
                        <tbody>
                        @forelse ($items as $item)
                            <tr wire:key="wa-cost-item-{{ $item->id }}">
                                <td>{{ $item->provider_occurred_at ? \Illuminate\Support\Carbon::parse($item->provider_occurred_at)->format('Y-m-d H:i') : \Illuminate\Support\Carbon::parse($item->created_at)->format('Y-m-d H:i') }}</td>
                                <td>{{ $item->organization_name ?? 'Unmatched / unattributed' }}</td>
                                <td><code>{{ $item->provider_message_id ?? '—' }}</code></td>
                                <td>{{ $item->provider_cost_amount ?? '—' }}</td>
                                <td>{{ $item->provider_cost_currency ?? '—' }}</td>
                                <td>{{ $item->pricing_category ?? '—' }}</td>
                                <td>{{ ucfirst($item->reconciliation_status) }}</td>
                                <td>{{ $item->internal_cost_amount ?? '—' }}</td>
                                <td>{{ $item->internal_cost_currency ?? '—' }}</td>
                                <td>@if (is_array($item->discrepancy_details)) {{ implode(', ', array_keys($item->discrepancy_details)) }} @else — @endif</td>
                                <td>{{ $item->issue ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="empty">No reconciliation rows match these filters.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pagination-wrap">{{ $items->links() }}</div>
            </section>

            <section class="card">
                <h2>Recent imports</h2>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Imported at</th><th>File</th><th>Rows</th><th>Matched</th><th>Unmatched</th><th>Duplicates</th><th>Discrepancies</th><th>Invalid</th><th>Imported by</th></tr></thead>
                        <tbody>
                        @forelse ($batches as $batch)
                            <tr wire:key="wa-cost-batch-{{ $batch->id }}">
                                <td>{{ $batch->imported_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td>{{ $batch->source_filename }}</td>
                                <td>{{ number_format($batch->row_count) }}</td>
                                <td>{{ number_format($batch->matched_count) }}</td>
                                <td>{{ number_format($batch->unmatched_count) }}</td>
                                <td>{{ number_format($batch->duplicate_count) }}</td>
                                <td>{{ number_format($batch->discrepancy_count) }}</td>
                                <td>{{ number_format($batch->invalid_count) }}</td>
                                <td>{{ $batch->importer?->name ?? 'System / deleted user' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="empty">No imports yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
