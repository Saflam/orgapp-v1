<div>
    <div class="page-head"><div><h1>{{ $organization->name }}</h1><div class="muted">{{ $organization->code }} · {{ $organization->slug }}</div></div><div class="actions"><a class="btn secondary" href="{{ route('core.organizations.identifications', $organization) }}">Identification requirements</a><a class="btn secondary" href="{{ route('core.organizations.units', $organization) }}">Manage units</a><a class="btn secondary" href="{{ route('core.organizations.index') }}">← Organizations</a><button class="btn {{ $organization->is_active ? 'danger' : '' }}" wire:click="toggleOrganizationStatus" wire:confirm="{{ $organization->is_active ? 'Deactivate' : 'Activate' }} this organization?">{{ $organization->is_active ? 'Deactivate organization' : 'Activate organization' }}</button></div></div>
    <section class="card">
        <h2>General settings</h2>
        <p class="muted">Edit the organization identity and regional preferences.</p>
        <form wire:submit="saveOrganizationDetails">
            <div class="grid">
                <div class="field"><label for="organization-name">Organization name</label><input id="organization-name" type="text" wire:model="name" required maxlength="255">@error('name')<span class="error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="organization-timezone">Time zone</label><input id="organization-timezone" type="text" wire:model="timezone" required placeholder="Asia/Kolkata">@error('timezone')<span class="error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="organization-currency">Currency (ISO 4217)</label><input id="organization-currency" type="text" wire:model="currency" required minlength="3" maxlength="3" placeholder="INR">@error('currency')<span class="error">{{ $message }}</span>@enderror</div>
                <div><div class="muted">Status</div><span class="pill {{ $organization->is_active ? 'ok' : 'off' }}">{{ $organization->is_active ? 'Active' : 'Inactive' }}</span></div>
            </div>
            <div class="actions"><button class="btn" type="submit" wire:loading.attr="disabled" wire:target="saveOrganizationDetails"><span wire:loading.remove wire:target="saveOrganizationDetails">Save organization details</span><span wire:loading wire:target="saveOrganizationDetails">Saving…</span></button></div>
        </form>
    </section>

    <section class="card">
        <h2>Organization UI theme</h2>
        <p class="muted">Choose the appearance used by the shared admin interface for this organization.</p>
        <form wire:submit="saveTheme">
            <div class="field">
                <label for="organization-theme">Preferred theme</label>
                <select id="organization-theme" wire:model="theme">
                    <option value="system">Use device preference</option>
                    <option value="light">Light</option>
                    <option value="dark">Dark</option>
                </select>
                @error('theme')<span class="error">{{ $message }}</span>@enderror
            </div>
            <button class="btn" type="submit" wire:loading.attr="disabled" wire:target="saveTheme">
                <span wire:loading.remove wire:target="saveTheme">Save theme</span>
                <span wire:loading wire:target="saveTheme">Saving…</span>
            </button>
        </form>
    </section>

    <section class="card">
        <h2>Organization branding</h2>
        <p class="muted">Upload a logo and favicon for organization-specific branding. PNG, JPG, JPEG or WebP logo (up to 2 MB); PNG or ICO favicon (up to 1 MB).</p>
        <form wire:submit="saveBranding">
            <div class="grid">
                <div class="field"><label for="organization-logo">Logo</label>@if ($logoUrl)<img src="{{ $logoUrl }}" alt="Current organization logo" style="max-width:220px;max-height:90px;object-fit:contain;object-position:left">@endif<input id="organization-logo" type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp">@if ($logo && method_exists($logo, 'temporaryUrl'))<img src="{{ $logo->temporaryUrl() }}" alt="Logo preview" style="max-width:220px;max-height:90px;object-fit:contain;object-position:left">@endif @error('logo')<span class="error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="organization-favicon">Favicon</label>@if ($faviconUrl)<img src="{{ $faviconUrl }}" alt="Current organization favicon" style="width:40px;height:40px;object-fit:contain">@endif<input id="organization-favicon" type="file" wire:model="favicon" accept="image/png,.ico">@error('favicon')<span class="error">{{ $message }}</span>@enderror</div>
            </div>
            <div class="actions"><button class="btn" type="submit" wire:loading.attr="disabled" wire:target="saveBranding,logo,favicon"><span wire:loading.remove wire:target="saveBranding">Save branding</span><span wire:loading wire:target="saveBranding">Uploading…</span></button><span class="muted" wire:loading wire:target="logo,favicon">Preparing upload…</span></div>
        </form>
    </section>

    <section class="card">
        <h2>Organization Super Admin</h2>
        <p class="muted">Update the existing assigned Super Admin account. This does not replace the user or change their role assignment.</p>
        @if ($hasSuperAdmin)
            <form wire:submit="saveSuperAdminDetails">
                <div class="grid">
                    <div class="field"><label for="super-admin-name">Full name</label><input id="super-admin-name" type="text" wire:model="superAdminName" required maxlength="255">@error('superAdminName')<span class="error">{{ $message }}</span>@enderror</div>
                    <div class="field"><label for="super-admin-email">Email address</label><input id="super-admin-email" type="email" wire:model="superAdminEmail" required maxlength="255">@error('superAdminEmail')<span class="error">{{ $message }}</span>@enderror</div>
                </div>
                <div class="actions"><button class="btn" type="submit" wire:loading.attr="disabled" wire:target="saveSuperAdminDetails"><span wire:loading.remove wire:target="saveSuperAdminDetails">Save Super Admin details</span><span wire:loading wire:target="saveSuperAdminDetails">Saving…</span></button></div>
            </form>
        @else
            <p class="muted">No active Super Admin assignment was found for this organization.</p>
        @endif
    </section>

    <section class="card"><h2>Module management</h2><p class="muted">This section is System Admin-only. Required modules remain locked.</p><div class="table-wrap"><table><thead><tr><th>Module</th><th>Requirement</th><th>Status</th><th>Action</th></tr></thead><tbody>
    @foreach ($modules as $module)<tr><td><strong>{{ $module['module'] }}</strong></td><td>{{ $module['is_required'] ? 'Required' : 'Optional' }}</td><td><span class="pill {{ $module['is_enabled'] ? 'ok' : 'off' }}">{{ $module['is_enabled'] ? 'Enabled' : 'Disabled' }}</span></td><td>@if ($module['is_required'])<span class="muted">Locked</span>@else<button class="btn secondary" wire:click="toggleModule('{{ $module['module'] }}')" wire:confirm="Change the {{ $module['module'] }} module state?">{{ $module['is_enabled'] ? 'Disable' : 'Enable' }}</button>@endif</td></tr>@endforeach
    </tbody></table></div></section>
    <section class="card"><h2>System-default notification access</h2><p class="muted">Only System Admin can grant this access. It does not expose or display provider credentials.</p>
        @foreach (['sms' => 'SMS', 'whatsapp' => 'WhatsApp'] as $channel => $label)
            @php($allowed = (bool) ($channels->get($channel)?->use_system_default ?? false))
            <div class="checkrow"><div style="flex:1"><strong>{{ $label }}</strong><div class="muted">{{ $allowed ? 'System-default access allowed' : 'System-default access not allowed' }}</div></div><button class="btn secondary" wire:click="setSystemDefaultAccess('{{ $channel }}', {{ $allowed ? 'false' : 'true' }})">{{ $allowed ? 'Revoke access' : 'Grant access' }}</button></div>
        @endforeach
    </section>
</div>
