<div>
    <div class="page-head">
        <div><h1>WhatsApp usage records</h1><div class="muted">Accepted sends and delivery outcomes, filterable by organization and period.</div></div>
        <a class="btn secondary" href="/system/whatsapp-usage">Back to WhatsApp usage</a>
    </div>
    <div class="long-page-layout">
        @include('core::livewire.system.partials.whatsapp-management-nav', ['active' => 'records'])
        <div class="long-page-content">
            <section class="card">
                <h2>Accepted sends in selected period</h2>
                <p class="muted">{{ number_format($totalAccepted) }} accepted send records match the selected filters.</p>
                <div class="grid">
                    <div class="field"><label for="usage-organization">Organization</label><select id="usage-organization" wire:model.live="organizationId"><option value="">All organizations</option>@foreach ($organizations as $organization)<option value="{{ $organization->id }}">{{ $organization->name }}</option>@endforeach</select></div>
                    <div class="date-filter-row" style="display:grid;grid-template-columns:repeat(2,minmax(0,160px));gap:12px;max-width:332px;align-items:end">
                        <div class="field" style="min-width:0"><label for="usage-from">From</label><input id="usage-from" type="date" wire:model.live="fromDate" style="width:100%;min-width:0"></div>
                        <div class="field" style="min-width:0"><label for="usage-to">To</label><input id="usage-to" type="date" wire:model.live="toDate" style="width:100%;min-width:0"></div>
                    </div>
                </div>
                <div class="table-wrap"><table><thead><tr><th>Organization</th><th>Service</th><th>Channel source</th><th>API status</th><th>Delivery status</th><th>Messages</th></tr></thead><tbody>
                    @forelse ($rows as $row)
                        <tr wire:key="wa-usage-row-{{ md5(($row->organization_id ?? 'central').'-'.$row->service_category.'-'.$row->channel_source.'-'.$row->status.'-'.($row->delivery_status ?? 'unknown')) }}"><td>{{ $row->organization_name ?? 'Unattributed / central' }}</td><td>{{ strtoupper($row->service_category) }}</td><td>{{ ucfirst($row->channel_source) }}</td><td>{{ ucfirst($row->status) }}</td><td>{{ $row->delivery_status ? ucfirst($row->delivery_status) : 'Awaiting webhook' }}</td><td>{{ number_format($row->message_count) }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="empty">No WhatsApp usage records match these filters.</td></tr>
                    @endforelse
                </tbody></table></div>
                <div class="pagination-wrap">{{ $rows->links() }}</div>
            </section>
        </div>
    </div>
</div>
