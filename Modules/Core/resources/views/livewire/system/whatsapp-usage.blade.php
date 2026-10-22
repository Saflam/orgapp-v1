<div>
    <div class="page-head"><div><h1>WhatsApp usage</h1><div class="muted">Saflivo's tenant-attributed WhatsApp send ledger.</div></div><a class="btn secondary" href="/system/whatsapp-settings">WhatsApp settings</a></div>
    @if ($limitActionMessage !== '')<div class="notice" role="status">{{ $limitActionMessage }}</div>@endif
    <div class="long-page-layout">
        @include('core::livewire.system.partials.whatsapp-management-nav', ['active' => 'usage'])
        <div class="long-page-content">
    <div class="actions whatsapp-section-jumps" aria-label="WhatsApp usage page sections">
        <a class="btn secondary" href="#wa-monthly-limits">Organization limits</a>
        <a class="btn secondary" href="#wa-pending-requests">Pending requests</a>
        <a class="btn secondary" href="#wa-limit-audit">Limit audit history</a>
        <a class="btn secondary" href="#wa-usage-notes">How to read usage</a>
    </div>
    <section class="card" id="wa-monthly-limits">
        <h2>Organization monthly limits</h2>
        <p class="muted">Limits apply across Saflivo-default and organization-owned WhatsApp channels. Organizations without a configured limit remain unlimited.</p>
        <div class="table-wrap"><table><thead><tr><th>Organization</th><th>Monthly limit</th><th>Warning</th><th>Action</th></tr></thead><tbody>
            @foreach ($organizations as $organization)
                <tr wire:key="wa-limit-{{ $organization->id }}">
                    <td>{{ $organization->name }}</td>
                    <td>
                        <label><input type="checkbox" wire:model.live="limits.{{ $organization->id }}.unlimited"> Unlimited</label>
                        <input type="number" min="1" max="10000000" wire:model="limits.{{ $organization->id }}.monthly_limit" @disabled($limits[$organization->id]['unlimited']) aria-label="Monthly WhatsApp limit for {{ $organization->name }}">
                        @error('limits.' . $organization->id . '.monthly_limit')<div class="field-error">{{ $message }}</div>@enderror
                    </td>
                    <td><select wire:model="limits.{{ $organization->id }}.warning_threshold" aria-label="Warning threshold for {{ $organization->name }}"><option value="70">70%</option><option value="80">80%</option><option value="90">90%</option></select></td>
                    <td><button class="btn secondary" type="button" wire:click="saveOrganizationLimit({{ $organization->id }})" wire:loading.attr="disabled">Save limit</button></td>
                </tr>
            @endforeach
        </tbody></table></div>
    </section>
    <section class="card" id="wa-pending-requests">
        <h2>Pending usage-limit increase requests</h2>
        @forelse ($pendingLimitRequests as $request)
            <div class="card" wire:key="wa-limit-request-{{ $request->id }}">
                <h3>{{ $request->organization->name }} — {{ number_format($request->current_limit) }} to {{ number_format($request->requested_limit) }} messages/month</h3>
                <p><strong>Requested by:</strong> {{ $request->requester->name ?? $request->requester->email ?? 'User' }} · {{ $request->created_at?->format('Y-m-d H:i') }}</p>
                <p>{{ $request->reason }}</p>
                <div class="grid">
                    <div class="field"><label>Granted monthly limit (leave blank to grant requested amount)</label><input type="number" min="{{ $request->current_limit + 1 }}" max="10000000" wire:model="requestDecisions.{{ $request->id }}.granted_limit"></div>
                    <div class="field"><label>Decision note</label><textarea wire:model="requestDecisions.{{ $request->id }}.review_note" maxlength="2000"></textarea></div>
                </div>
                @error('requestDecisions.' . $request->id . '.granted_limit')<div class="field-error">{{ $message }}</div>@enderror
                <div class="actions"><button class="btn" type="button" wire:click="reviewLimitRequest({{ $request->id }}, 'approve')">Approve increase</button><button class="btn secondary" type="button" wire:click="reviewLimitRequest({{ $request->id }}, 'reject')">Reject request</button></div>
            </div>
        @empty
            <p class="muted">There are no pending usage-limit increase requests.</p>
        @endforelse
    </section>
    <section class="card" id="wa-limit-audit">
        <h2>Usage-limit audit history</h2>
        <div class="table-wrap"><table><thead><tr><th>Date</th><th>Organization</th><th>Previous limit</th><th>New limit</th><th>Action</th><th>Changed by</th><th>Reason / note</th></tr></thead><tbody>
            @forelse ($limitAudits as $audit)
                <tr><td>{{ $audit->created_at }}</td><td>{{ $audit->organization_name }}</td><td>{{ $audit->previous_limit === null ? 'Unlimited / not set' : number_format($audit->previous_limit) }}</td><td>{{ $audit->new_limit === null ? 'Unlimited' : number_format($audit->new_limit) }}</td><td>{{ str_replace('_', ' ', ucfirst($audit->action)) }}</td><td>{{ $audit->changed_by_name ?? 'System' }}</td><td>{{ $audit->reason ?: '—' }}</td></tr>
            @empty<tr><td colspan="7" class="empty">No limit changes recorded yet.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    <section class="card" id="wa-usage-notes"><h2>How to interpret these figures</h2><ul><li>Saflivo records each accepted Meta send request with the organization, service category and source channel.</li><li>OTP, Utility and Marketing usage are separated to support service-level charging.</li><li>Failed API attempts are recorded separately and should not be treated as delivered/billable messages without reconciliation.</li><li>Meta does not return a per-Saflivo-organization invoice when multiple tenants share a WABA or phone number. These are Saflivo-attributed counts, not Meta's exact per-tenant costs.</li><li>Delivery/read/failure status is updated from verified Meta message-status webhooks. To reconcile delivered/billable messages and actual Meta pricing, compare WABA-level billing/analytics. The exact per-tenant charge must be calculated from Saflivo's own usage ledger and agreed rates.</li></ul></section>
        </div>
    </div>
</div>
