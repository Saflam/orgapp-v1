<div>
    <div class="page-head"><div><h1>Add organization</h1><div class="muted">Create the organization and its initial administrator.</div></div><a href="{{ route('core.organizations.index') }}">← Back to organizations</a></div>
    <ol class="steps"><li class="{{ $step === 1 ? 'current' : '' }}">1. Details</li><li class="{{ $step === 2 ? 'current' : '' }}">2. Super Admin</li><li class="{{ $step === 3 ? 'current' : '' }}">3. Modules & access</li><li class="{{ $step === 4 ? 'current' : '' }}">4. Review</li></ol>
    <form wire:submit="{{ $step === 4 ? 'create' : 'next' }}">
        @if ($step === 1)
            <section class="card"><h2>Organization details</h2>
                <div class="field"><label>Organization name</label><input wire:model="organization.name" autocomplete="organization"><div class="error">@error('organization.name'){{ $message }}@enderror</div></div>
                <div class="grid"><div class="field"><label>Organization code</label><input wire:model="organization.code" autocomplete="off"><div class="error">@error('organization.code'){{ $message }}@enderror</div></div><div class="field"><label>Timezone</label><input wire:model="organization.timezone" placeholder="Asia/Kolkata"><div class="error">@error('organization.timezone'){{ $message }}@enderror</div></div></div>
                <div class="field"><label>Currency (ISO 4217)</label><input wire:model="organization.currency" maxlength="3" placeholder="INR"><div class="error">@error('organization.currency'){{ $message }}@enderror</div></div>
            </section>
        @elseif ($step === 2)
            <section class="card"><h2>Initial Organization Super Admin</h2><p class="muted">The initial administrator will be provisioned for this organization.</p>
                <div class="field"><label>Full name</label><input wire:model="super_admin.name" autocomplete="name"><div class="error">@error('super_admin.name'){{ $message }}@enderror</div></div>
                <div class="grid"><div class="field"><label>Email</label><input type="email" wire:model="super_admin.email" autocomplete="email"><div class="error">@error('super_admin.email'){{ $message }}@enderror</div></div><div class="field"><label>Phone (optional)</label><input wire:model="super_admin.phone" autocomplete="tel"><div class="error">@error('super_admin.phone'){{ $message }}@enderror</div></div></div>
            </section>
        @elseif ($step === 3)
            <section class="card"><h2>Module activation</h2><p class="muted">Core and Member are mandatory and cannot be disabled. Optional modules start disabled unless selected here.</p>
                <div class="checkrow"><input type="checkbox" checked disabled id="module-core"><label for="module-core"><strong>Core</strong> — Required module</label></div>
                <div class="checkrow"><input type="checkbox" checked disabled id="module-member"><label for="module-member"><strong>Member</strong> — Required module</label></div>
                @forelse ($availableModules as $module)
                    <div class="checkrow"><input type="checkbox" id="module-{{ $module }}" value="{{ $module }}" wire:model="modules"><label for="module-{{ $module }}">{{ $module }}</label></div>
                @empty <p class="muted">No optional modules are currently available.</p> @endforelse
            </section>
            <section class="card"><h2>System-default notification access</h2><p class="muted">Only the System Admin can grant or revoke an organization's access to system-configured SMS and WhatsApp channels. Organization Super Admins cannot change these access flags.</p>
                <div class="checkrow"><input id="access-sms" type="checkbox" wire:model="notification_channels.sms.use_system_default"><label for="access-sms">Allow system-default SMS for this organization</label></div>
                <div class="checkrow"><input id="access-whatsapp" type="checkbox" wire:model="notification_channels.whatsapp.use_system_default"><label for="access-whatsapp">Allow system-default WhatsApp for this organization</label></div>
            </section>
        @else
            <section class="card"><h2>Review organization</h2>
                <h3>Organization</h3><p><strong>{{ $organization['name'] }}</strong> · {{ $organization['code'] }}</p><p class="muted">{{ $organization['timezone'] }} · {{ strtoupper($organization['currency']) }}</p>
                <h3>Initial Super Admin</h3><p>{{ $super_admin['name'] }} · {{ $super_admin['email'] }}</p>
                <h3>Modules</h3><p>Core (required), Member (required){{ count($modules) ? ', ' . implode(', ', $modules) : '' }}</p>
                <h3>System-default notification access</h3><p>SMS: {{ $notification_channels['sms']['use_system_default'] ? 'Allowed' : 'Not allowed' }} · WhatsApp: {{ $notification_channels['whatsapp']['use_system_default'] ? 'Allowed' : 'Not allowed' }}</p>
                <p class="muted">Organization creation provisions the Super Admin and initializes modules and notification access in a database transaction.</p>
            </section>
        @endif
        <div class="actions">@if ($step > 1)<button class="btn secondary" type="button" wire:click="back">Back</button>@endif @if ($step < 4)<button class="btn" type="submit">Continue</button>@else<button class="btn" type="submit" wire:loading.attr="disabled">Create organization</button>@endif <span wire:loading class="muted">Processing…</span></div>
    </form>
</div>
