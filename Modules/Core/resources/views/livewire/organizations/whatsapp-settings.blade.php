<div>
    <div class="page-head"><div><h1>WhatsApp notification settings</h1><div class="muted">Configure this organization's own Meta WhatsApp channel when the System Admin has permitted it.</div></div></div>
    @if ($successMessage !== '')<div class="notice" role="status">{{ $successMessage }}</div>@endif
    @if ($connectionTestMessage !== '')<div class="{{ $connectionTestStatus === 'success' ? 'notice' : 'error' }}" role="status"><strong>{{ $connectionTestStatus === 'success' ? 'Connection test passed' : 'Connection test failed' }}:</strong> {{ $connectionTestMessage }}@if ($connectionTestPhoneNumber) <div>Meta phone: {{ $connectionTestPhoneNumber }}@if ($connectionTestVerifiedName) · Verified name: {{ $connectionTestVerifiedName }}@endif</div>@endif</div>@endif
    @if ($limitRequestMessage !== '')<div class="notice" role="status">{{ $limitRequestMessage }}</div>@endif
    <section class="card">
        <h2>WhatsApp monthly usage</h2>
        <p><strong>Messages used:</strong> {{ number_format($usage['used']) }}
            · <strong>Monthly limit:</strong> {{ $usage['limit'] === null ? 'Unlimited' : number_format($usage['limit']) }}
            · <strong>Remaining:</strong> {{ $usage['remaining'] === null ? 'Unlimited' : number_format($usage['remaining']) }}</p>
        @if ($usage['limit'] !== null)
            <div style="height:8px;background:var(--border,#e5e7eb);border-radius:99px;overflow:hidden;margin:10px 0"><div style="height:8px;width:{{ $usage['percentage'] }}%;background:{{ $usage['exhausted'] ? '#dc2626' : '#0d9488' }}"></div></div>
            <p class="muted">{{ $usage['percentage'] }}% used. Usage resets on {{ $usage['reset_at'] }}. Warning threshold: {{ $usage['warning_threshold'] }}%.</p>
        @else
            <p class="muted">No monthly WhatsApp limit is configured for this organization.</p>
        @endif
        @if ($usage['exhausted'])
            <h3>Request a monthly limit increase</h3>
            <p class="muted">WhatsApp sending is blocked after the limit is reached. Email OTP remains available where supported. A request does not change the limit until the System Admin approves it.</p>
            <form wire:submit="requestLimitIncrease">
                <div class="grid">
                    <div class="field"><label for="wa-request-limit">Requested new monthly limit</label><input id="wa-request-limit" type="number" min="{{ $usage['limit'] + 1 }}" max="10000000" wire:model="requestedMonthlyLimit" required></div>
                    <div class="field"><label for="wa-request-reason">Reason for increase</label><textarea id="wa-request-reason" wire:model="limitRequestReason" minlength="10" maxlength="2000" required></textarea></div>
                </div>
                <div class="actions"><button class="btn" type="submit" wire:loading.attr="disabled">Submit increase request</button></div>
            </form>
        @endif
        @if ($limitRequests->isNotEmpty())
            <h3>Limit increase requests</h3>
            <div class="table-wrap"><table><thead><tr><th>Date</th><th>Current limit</th><th>Requested limit</th><th>Status</th><th>Decision note</th></tr></thead><tbody>
                @foreach ($limitRequests as $request)
                    <tr><td>{{ $request->created_at?->format('Y-m-d H:i') }}</td><td>{{ $request->current_limit === null ? 'Unlimited' : number_format($request->current_limit) }}</td><td>{{ number_format($request->requested_limit) }}</td><td>{{ ucfirst($request->status) }}@if ($request->granted_limit) · granted {{ number_format($request->granted_limit) }}@endif</td><td>{{ $request->review_note ?: '—' }}</td></tr>
                @endforeach
            </tbody></table></div>
        @endif
    </section>
    @if (! $isAllowed)
        <section class="card"><h2>WhatsApp is not enabled</h2><p>The System Admin has not enabled WhatsApp for this organization. Contact the System Admin to request access. You cannot enable the feature from this page.</p></section>
    @else
        <section class="card">
            <h2>Service permissions</h2>
            <p><strong>OTP:</strong> Included when the System Admin allows WhatsApp. OTP is sent only when a verified phone number and a usable Authentication template/channel are available.</p>
            <p><strong>Utility:</strong> {{ $utilityAllowed ? 'Approved by System Admin' : 'Not approved — ask the System Admin' }}</p>
            <p><strong>Marketing:</strong> {{ $marketingAllowed ? 'Approved by System Admin' : 'Not approved — ask the System Admin' }}</p>
            <p><strong>Saflivo default fallback:</strong> {{ $useSystemDefault ? 'Allowed' : 'Not allowed' }}</p>
            <p><strong>Current OTP channel:</strong> {{ $effectiveSource === 'organization' ? 'Organization credentials' : ($effectiveSource === 'system' ? 'Saflivo default channel' : 'Unavailable until a permitted channel is configured') }}</p>
        </section>
        <section class="card">
            <h2>Organization-specific Meta credentials</h2>
            <p class="muted">If enabled and configured, this channel takes precedence over Saflivo's default. Credentials are encrypted; the saved access token is never shown again. Leave the token field blank to keep the stored token.</p>
            <form wire:submit="save">
                <div class="checkrow"><input id="org-wa-enabled" type="checkbox" wire:model="isEnabled"><label for="org-wa-enabled">Use this organization's own WhatsApp channel</label></div>
                <div class="grid">
                    <div class="field"><label for="org-wa-phone">Phone Number ID</label><input id="org-wa-phone" wire:model="phoneNumberId" autocomplete="off"></div>
                    <div class="field"><label for="org-wa-waba">WABA ID</label><input id="org-wa-waba" wire:model="wabaId" autocomplete="off"></div>
                    <div class="field"><label for="org-wa-token">Meta access token</label><input id="org-wa-token" type="password" wire:model="accessToken" autocomplete="new-password" placeholder="{{ $hasAccessToken ? 'Saved token exists — enter only to replace' : 'Paste access token' }}"></div>
                    <div class="field"><label for="org-wa-version">Graph API version</label><input id="org-wa-version" wire:model="apiVersion"></div>
                </div>
                <h3>Authentication template (OTP)</h3><div class="grid"><div class="field"><label for="org-wa-otp">Approved Authentication template name</label><input id="org-wa-otp" wire:model="otpTemplateName"></div><div class="field"><label for="org-wa-otplang">Language</label><input id="org-wa-otplang" wire:model="otpTemplateLanguage"></div></div>
                <h3>Utility template</h3><div class="grid"><div class="field"><label for="org-wa-util">Approved Utility template name</label><input id="org-wa-util" wire:model="utilityTemplateName" @disabled(! $utilityAllowed)></div><div class="field"><label for="org-wa-utillang">Language</label><input id="org-wa-utillang" wire:model="utilityTemplateLanguage"></div></div>
                <h3>Marketing template</h3><div class="grid"><div class="field"><label for="org-wa-marketing">Approved Marketing template name</label><input id="org-wa-marketing" wire:model="marketingTemplateName" @disabled(! $marketingAllowed)></div><div class="field"><label for="org-wa-marketlang">Language</label><input id="org-wa-marketlang" wire:model="marketingTemplateLanguage"></div></div>
                <div class="actions"><button class="btn secondary" type="button" wire:click="testOrganizationConnection" wire:loading.attr="disabled">Test connection</button><button class="btn" type="submit" wire:loading.attr="disabled">Save organization channel</button><span class="muted">Saved token: {{ $hasAccessToken ? 'Yes' : 'No' }}</span></div><p class="muted">Test Connection makes a read-only Meta API request. It does not send a message or consume WhatsApp message quota. Unsaved form values are tested; a blank token uses the saved token.</p>
            </form>
        </section>
        <section class="card"><h2>Important</h2><ul><li>Use a phone number and WABA belonging to the organization.</li><li>Each template must be approved in the WABA associated with the selected sender.</li><li>Organization-specific credentials take precedence only when the channel is enabled and the credentials for the requested service are complete.</li><li>If no eligible organization channel exists, Saflivo's default is used only when the System Admin permits it.</li></ul></section>
    @endif
</div>
