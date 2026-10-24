<div>
    @if ($successMessage !== '')
        <div class="notice" role="status" aria-live="polite">{{ $successMessage }}</div>
    @endif
    <div class="page-head"><div><h1>Organization settings</h1><div class="muted">Manage general settings for your organization.</div></div><div class="actions"><a class="btn secondary" href="{{ route('core.organization.identifications') }}">Identification requirements</a><a class="btn secondary" href="{{ route('core.organization.units') }}">Manage units</a><a class="btn secondary" href="{{ route('member.membership-types') }}">Membership types</a><a class="btn secondary" href="{{ route('member.payment-categories') }}">Member payment categories</a></div></div>
    <section class="card"><p class="muted">These settings apply only to the organization resolved for your current host and membership. Module management and system-default SMS/WhatsApp access are reserved for the System Admin.</p>
        <form wire:submit="save">
            <div class="field"><label>Organization name</label><input wire:model="name"><div class="error">@error('name'){{ $message }}@enderror</div></div>
            <div class="grid"><div class="field"><label>Timezone</label><input wire:model="timezone"><div class="error">@error('timezone'){{ $message }}@enderror</div></div><div class="field"><label>Currency (ISO 4217)</label><input wire:model="currency" maxlength="3"><div class="error">@error('currency'){{ $message }}@enderror</div></div></div>
            <div class="field"><label>Preferred UI theme</label><select wire:model="theme"><option value="system">Use system default</option><option value="light">Light</option><option value="dark">Dark</option></select><div class="error">@error('theme'){{ $message }}@enderror</div><div class="muted">The selected theme is applied to the shared Saflivo admin interface for this organization.</div></div>
            <div class="field"><label for="membership-expiry-reminder-days">Membership expiry reminders (days before expiry)</label><input id="membership-expiry-reminder-days" wire:model="membershipExpiryReminderDays" placeholder="30,7,1"><div class="error">@error('membershipExpiryReminderDays'){{ $message }}@enderror</div><div class="muted">Enter comma-separated days, for example 30,7,1. Use 0 to notify on the expiry date. Applies to this organization.</div></div>
            <button class="btn" type="submit" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Save settings</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </form>
    </section>

    <section class="card">
        <h2>Organization branding</h2>
        <p class="muted">Upload a logo and favicon for your organization's admin interface. Logo: PNG, JPG, JPEG or WebP up to 2 MB. Favicon: PNG or ICO up to 1 MB.</p>
        <form wire:submit="saveBranding">
            <div class="grid">
                <div class="field">
                    <label for="organization-logo">Logo</label>
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="Current organization logo" style="max-width:220px;max-height:90px;object-fit:contain;object-position:left;margin:4px 0 8px">
                    @endif
                    <input id="organization-logo" type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp">
                    @if ($logo && method_exists($logo, 'temporaryUrl'))
                        <img src="{{ $logo->temporaryUrl() }}" alt="Selected logo preview" style="max-width:220px;max-height:90px;object-fit:contain;object-position:left;margin-top:8px">
                    @endif
                    @error('logo')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="organization-favicon">Favicon</label>
                    @if ($faviconUrl)
                        <img src="{{ $faviconUrl }}" alt="Current organization favicon" style="width:40px;height:40px;object-fit:contain;margin:4px 0 8px">
                    @endif
                    <input id="organization-favicon" type="file" wire:model="favicon" accept="image/png,.ico">
                    @error('favicon')<span class="error">{{ $message }}</span>@enderror
                </div>
            </div>
            <button class="btn" type="submit" wire:loading.attr="disabled" wire:target="saveBranding,logo,favicon">
                <span wire:loading.remove wire:target="saveBranding">Save branding</span>
                <span wire:loading wire:target="saveBranding">Saving branding…</span>
            </button>
            <span class="muted" wire:loading wire:target="logo,favicon">Preparing upload…</span>
        </form>
    </section>
</div>
