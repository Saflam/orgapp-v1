<div>
    <div class="page-head">
        <div><h1>WhatsApp message history</h1><div class="muted">Searchable, organization-filtered history with delivery and webhook audit details. Message bodies and OTP values are not displayed.</div></div>
        <a class="btn secondary" href="/system/whatsapp-usage">Back to WhatsApp usage</a>
    </div>
    <div class="long-page-layout">
        @include('core::livewire.system.partials.whatsapp-management-nav', ['active' => 'history'])
        <div class="long-page-content">
            <section class="card">
                <h2>Filter message history</h2>
                <div class="grid">
                    <div class="field"><label for="history-organization">Organization</label><select id="history-organization" wire:model.live="organizationId"><option value="">All organizations</option>@foreach ($organizations as $organization)<option value="{{ $organization->id }}">{{ $organization->name }}</option>@endforeach</select></div>
                    <div class="field"><label for="history-service">Service category</label><select id="history-service" wire:model.live="serviceCategory"><option value="">All categories</option><option value="otp">OTP</option><option value="utility">Utility</option><option value="marketing">Marketing</option></select></div>
                    <div class="field"><label for="history-api-status">API status</label><select id="history-api-status" wire:model.live="apiStatus"><option value="">All API statuses</option><option value="accepted">Accepted</option><option value="failed">Failed</option></select></div>
                    <div class="field"><label for="history-status">Delivery status</label><select id="history-status" wire:model.live="deliveryStatus"><option value="">All delivery statuses</option><option value="awaiting">Awaiting webhook</option><option value="sent">Sent</option><option value="delivered">Delivered</option><option value="read">Read</option><option value="failed">Failed</option></select></div>
                    <div class="field"><label for="history-search">Search</label><input id="history-search" type="search" wire:model.live.debounce.400ms="search" placeholder="Meta message ID, title, phone"></div>
                    <div class="date-filter-row" style="display:grid;grid-template-columns:repeat(2,minmax(0,160px));gap:12px;max-width:332px;align-items:end">
                        <div class="field" style="min-width:0"><label for="history-from">From</label><input id="history-from" type="date" wire:model.live="fromDate" style="width:100%;min-width:0"></div>
                        <div class="field" style="min-width:0"><label for="history-to">To</label><input id="history-to" type="date" wire:model.live="toDate" style="width:100%;min-width:0"></div>
                    </div>
                </div>
            </section>

            <section class="card">
                <h2>Message records</h2>
                <p class="muted">{{ number_format($messages->total()) }} records match these filters. Phone numbers are masked; OTP values and message bodies are not shown.</p>
                <div class="table-wrap"><table><thead><tr><th>Time</th><th>Organization</th><th>Recipient</th><th>Category</th><th>Channel</th><th>API status</th><th>Delivery</th><th>Attempts</th><th>Meta message ID</th><th>Details</th></tr></thead><tbody>
                    @forelse ($messages as $message)
                        <tr wire:key="wa-message-{{ $message->id }}">
                            <td>{{ $message->accepted_at ?: $message->created_at }}</td>
                            <td>{{ $message->organization_name ?? 'Unattributed / central' }}</td>
                            <td>{{ $message->recipient_masked }}</td>
                            <td>{{ strtoupper($message->service_category) }}</td>
                            <td>{{ ucfirst($message->channel_source) }}</td>
                            <td>{{ ucfirst($message->api_status) }}</td>
                            <td>{{ $message->delivery_status ? ucfirst($message->delivery_status) : 'Awaiting webhook' }}</td>
                            <td>{{ $message->retry_count }}</td>
                            <td>{{ $message->provider_message_id ? \Illuminate\Support\Str::limit($message->provider_message_id, 24) : 'Not assigned' }}</td>
                            <td><button type="button" class="btn secondary" wire:click="toggleAudit({{ $message->id }})">{{ $selectedAuditUsageId === $message->id ? 'Hide audit' : 'View audit' }}</button></td>
                        </tr>
                        @if ($selectedAuditUsageId === $message->id)
                            <tr wire:key="wa-message-audit-{{ $message->id }}"><td colspan="10">
                                <div class="card">
                                    <h3>Delivery and audit details</h3>
                                    @if ($message->delivery_error_detail)<p><strong>Latest failure detail:</strong> {{ $message->delivery_error_detail }}</p>@endif
                                    <p><strong>Accepted:</strong> {{ $message->accepted_at ?: 'Not recorded' }} · <strong>Delivered:</strong> {{ $message->delivered_at ?: 'Not recorded' }}</p>
                                    <p><strong>Pricing category:</strong> {{ $message->meta_pricing_category ?: 'Not reported' }} · <strong>Billable:</strong> {{ is_null($message->meta_billable) ? 'Not reported' : ($message->meta_billable ? 'Yes' : 'No') }}</p>
                                    <h4>Meta webhook event history</h4>
                                    @if ($auditEvents->isEmpty())
                                        <p class="muted">No matching webhook events have been recorded for this message ID.</p>
                                    @else
                                        <div class="table-wrap"><table><thead><tr><th>Provider timestamp</th><th>Status</th><th>Error code</th><th>Error</th><th>Pricing category</th><th>Billable</th><th>Processed at</th></tr></thead><tbody>
                                            @foreach ($auditEvents as $event)
                                                <tr wire:key="wa-audit-event-{{ $event->status }}-{{ $loop->index }}"><td>{{ $event->provider_timestamp ?: 'Not supplied' }}</td><td>{{ ucfirst($event->status) }}</td><td>{{ $event->error_code ?: '—' }}</td><td>{{ $event->error_title ?: '—' }}</td><td>{{ $event->pricing_category ?: '—' }}</td><td>{{ is_null($event->billable) ? '—' : ($event->billable ? 'Yes' : 'No') }}</td><td>{{ $event->processed_at ?: '—' }}</td></tr>
                                            @endforeach
                                        </tbody></table></div>
                                    @endif
                                </div>
                            </td></tr>
                        @endif
                    @empty
                        <tr><td colspan="10" class="empty">No WhatsApp message records match these filters.</td></tr>
                    @endforelse
                </tbody></table></div>
                <div class="pagination-wrap">{{ $messages->links() }}</div>
            </section>
        </div>
    </div>
</div>
