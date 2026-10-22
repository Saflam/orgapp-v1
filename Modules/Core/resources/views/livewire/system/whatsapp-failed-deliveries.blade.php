<div>
    <div class="page-head">
        <div><h1>Failed WhatsApp deliveries</h1><div class="muted">Review delivery failures across organizations. Recipient phone numbers are masked.</div></div>
        <a class="btn secondary" href="/system/whatsapp-usage">Back to WhatsApp usage</a>
    </div>
    <div class="long-page-layout">
        @include('core::livewire.system.partials.whatsapp-management-nav', ['active' => 'failed'])
        <div class="long-page-content">
            <section class="card">
                <h2>Failed deliveries</h2>
                <div class="grid">
                    <div class="field"><label for="failed-organization">Organization</label><select id="failed-organization" wire:model.live="organizationId"><option value="">All organizations</option>@foreach ($organizations as $organization)<option value="{{ $organization->id }}">{{ $organization->name }}</option>@endforeach</select></div>
                    <div class="date-filter-row" style="display:grid;grid-template-columns:repeat(2,minmax(0,160px));gap:12px;max-width:332px;align-items:end">
                        <div class="field" style="min-width:0"><label for="failed-from">From</label><input id="failed-from" type="date" wire:model.live="fromDate" style="width:100%;min-width:0"></div>
                        <div class="field" style="min-width:0"><label for="failed-to">To</label><input id="failed-to" type="date" wire:model.live="toDate" style="width:100%;min-width:0"></div>
                    </div>
                </div>
                <div class="table-wrap"><table><thead><tr><th>Date</th><th>Organization</th><th>Recipient</th><th>Message</th><th>Provider message ID</th><th>Failure reason</th></tr></thead><tbody>
                    @forelse ($failedDeliveries as $failed)
                        <tr wire:key="wa-failed-{{ $failed->id }}"><td>{{ $failed->created_at }}</td><td>{{ $failed->organization_name ?? 'Unattributed / central' }}</td><td>{{ $failed->recipient_masked }}</td><td>{{ $failed->title }}</td><td>{{ $failed->provider_message_id ?: '—' }}</td><td>{{ $failed->error ?: 'No failure details available' }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="empty">No failed WhatsApp deliveries match these filters.</td></tr>
                    @endforelse
                </tbody></table></div>
                <div class="pagination-wrap">{{ $failedDeliveries->links() }}</div>
            </section>
        </div>
    </div>
</div>
