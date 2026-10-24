<div>
    <div class="page-head">
        <div><h1>WhatsApp settings</h1><div class="muted">Configure Saflivo's default Meta Cloud API channel and approve organization service access.</div></div>
        <div class="actions page-head-actions"><a class="btn secondary" href="/system/whatsapp-message-history">Message history</a><a class="btn secondary" href="/system/whatsapp-templates">WhatsApp template guide</a></div>
    </div>
    @if ($successMessage !== '')<div class="notice" role="status">{{ $successMessage }}</div>@endif
    @if ($connectionTestMessage !== '')<div class="{{ $connectionTestStatus === 'success' ? 'notice' : 'error' }}" role="status"><strong>{{ $connectionTestStatus === 'success' ? 'Connection test passed' : 'Connection test failed' }}:</strong> {{ $connectionTestMessage }}@if ($connectionTestPhoneNumber) <div>Meta phone: {{ $connectionTestPhoneNumber }}@if ($connectionTestVerifiedName) · Verified name: {{ $connectionTestVerifiedName }}@endif</div>@endif</div>@endif
    <div class="long-page-layout">
        @include('core::livewire.system.partials.whatsapp-management-nav', ['active' => 'settings'])
        <div class="long-page-content">
    <section class="card">
        <h2>Saflivo default WhatsApp channel</h2>
        <p class="muted">Credentials are encrypted. The access token is never displayed after saving; leave it blank to retain the stored token.</p>
        <form wire:submit="saveDefaultChannel">
            <div class="checkrow"><input id="wa-default-enabled" type="checkbox" wire:model="isEnabled"><label for="wa-default-enabled">Enable Saflivo's default WhatsApp channel</label></div>
            <div class="grid">
                <div class="field"><label for="wa-phone-id">Phone Number ID</label><input id="wa-phone-id" wire:model="phoneNumberId" autocomplete="off">@error('phoneNumberId')<span class="error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="wa-waba-id">WhatsApp Business Account ID (WABA ID)</label><input id="wa-waba-id" wire:model="wabaId" autocomplete="off"></div>
                <div class="field"><label for="wa-token">Permanent/system-user access token</label><input id="wa-token" type="password" wire:model="accessToken" autocomplete="new-password" placeholder="{{ $hasAccessToken ? 'Saved token exists — enter only to replace' : 'Paste Meta access token' }}">@error('accessToken')<span class="error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="wa-version">Graph API version</label><input id="wa-version" wire:model="apiVersion" placeholder="v23.0">@error('apiVersion')<span class="error">{{ $message }}</span>@enderror</div>
            </div>
            <h3>Authentication (OTP) template — required for WhatsApp OTP</h3>
            <div class="grid"><div class="field"><label for="wa-otp-template">Approved Authentication template name</label><input id="wa-otp-template" wire:model="otpTemplateName">@error('otpTemplateName')<span class="error">{{ $message }}</span>@enderror</div><div class="field"><label for="wa-otp-language">Template language</label><input id="wa-otp-language" wire:model="otpTemplateLanguage"></div></div>
            <h3>Utility template</h3>
            <div class="grid"><div class="field"><label for="wa-utility-template">Approved Utility template name</label><input id="wa-utility-template" wire:model="utilityTemplateName"></div><div class="field"><label for="wa-utility-language">Template language</label><input id="wa-utility-language" wire:model="utilityTemplateLanguage"></div></div>
            <h3>Marketing template</h3>
            <div class="grid"><div class="field"><label for="wa-marketing-template">Approved Marketing template name</label><input id="wa-marketing-template" wire:model="marketingTemplateName" placeholder="Configure when marketing is approved"></div><div class="field"><label for="wa-marketing-language">Template language</label><input id="wa-marketing-language" wire:model="marketingTemplateLanguage"></div></div>
            <div class="actions"><button class="btn secondary" type="button" wire:click="testDefaultConnection" wire:loading.attr="disabled">Test connection</button><button class="btn" type="submit" wire:loading.attr="disabled">Save default channel</button><span class="muted">Token stored: {{ $hasAccessToken ? 'Yes' : 'No' }}</span></div><p class="muted">Test Connection makes a read-only Meta API request. It does not send a message or consume WhatsApp message quota. Unsaved form values are tested; a blank token uses the saved token.</p>
            @error('')<span class="error">{{ $message }}</span>@enderror
        </form>
    </section>
    <section class="card">
        <h2>Organization WhatsApp permissions</h2>
        <p class="muted">Allowing WhatsApp includes OTP. Utility and Marketing remain disabled until separately approved. Saflivo's default channel is only available to an organization when both WhatsApp access and default-channel access are allowed.</p>
        <div class="table-wrap"><table><thead><tr><th>Organization</th><th>WhatsApp feature / OTP</th><th>Saflivo default</th><th>Utility</th><th>Marketing</th><th>Action</th></tr></thead><tbody>
        @foreach (\Modules\Core\Models\Organization::query()->orderBy('name')->get() as $organization)
            <tr wire:key="whatsapp-org-{{ $organization->id }}"><td>{{ $organization->name }}<div class="muted">{{ $organization->slug }}</div></td>
                <td><input aria-label="Allow WhatsApp for {{ $organization->name }}" type="checkbox" wire:model="permissions.{{ $organization->id }}.is_allowed"></td>
                <td><input aria-label="Allow default channel for {{ $organization->name }}" type="checkbox" wire:model="permissions.{{ $organization->id }}.use_system_default" @disabled(!($permissions[$organization->id]['is_allowed'] ?? false))></td>
                <td><input aria-label="Approve Utility for {{ $organization->name }}" type="checkbox" wire:model="permissions.{{ $organization->id }}.utility_allowed" @disabled(!($permissions[$organization->id]['is_allowed'] ?? false))></td>
                <td><input aria-label="Approve Marketing for {{ $organization->name }}" type="checkbox" wire:model="permissions.{{ $organization->id }}.marketing_allowed" @disabled(!($permissions[$organization->id]['is_allowed'] ?? false))></td>
                <td><button class="btn secondary" type="button" wire:click="saveOrganizationPermissions({{ $organization->id }})">Save</button></td></tr>
        @endforeach
        @if (\Modules\Core\Models\Organization::query()->count() === 0)<tr><td colspan="6" class="empty">No organizations have been created yet.</td></tr>@endif
        </tbody></table></div>
    </section>
    <section class="card"><h2>Usage and charging</h2><p>Open <a href="/system/whatsapp-usage"><strong>WhatsApp usage</strong></a> to see internally attributed message counts by organization, service and channel source. Saflivo records these sends for service-level charging. This is not a Meta-provided per-tenant bill; Meta account billing must be reconciled separately.</p></section>
        </div>
    </div>
</div>
