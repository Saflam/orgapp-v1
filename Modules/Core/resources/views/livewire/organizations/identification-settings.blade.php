<div>
    @if ($successMessage !== '')
        <div class="notice" role="status" aria-live="polite">{{ $successMessage }}</div>
    @endif

    <div class="page-head">
        <div>
            <h1>Identification requirements</h1>
            <div class="muted">Configure which identification details and proof documents applicants must provide for {{ $organization->name }}.</div>
        </div>
        <div class="actions">
            @if (auth()->user()?->is_system_admin)
                <a class="btn secondary" href="{{ route('core.organizations.manage', $organization) }}">← Back to organization management</a>
            @else
                <a class="btn secondary" href="{{ route('core.organization.settings') }}">← Back to organization settings</a>
            @endif
        </div>
    </div>

    <section class="card">
        <h2>Add identification type</h2>
        <p class="muted">Add a reusable identification type to Saflivo's shared catalog, then enable and configure it for this organization below. Adding it here does not automatically enable it for other organizations.</p>
        <form wire:submit="addIdentificationType">
            <div class="field">
                <label for="identification-type-name">Name</label>
                <input id="identification-type-name" type="text" wire:model="newTypeName" maxlength="255" required placeholder="e.g. Aadhaar Card">
                @error('newTypeName') <span class="error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label for="identification-type-description">Description (optional)</label>
                <textarea id="identification-type-description" wire:model="newTypeDescription" maxlength="2000" rows="2"></textarea>
                @error('newTypeDescription') <span class="error">{{ $message }}</span> @enderror
            </div>
            <div class="actions">
                <button class="btn" type="submit" wire:loading.attr="disabled" wire:target="addIdentificationType">
                    <span wire:loading.remove wire:target="addIdentificationType">Add identification type</span>
                    <span wire:loading wire:target="addIdentificationType">Adding…</span>
                </button>
            </div>
        </form>
    </section>

    @if (count($settings) === 0)
        <section class="card">
            <p>No identification types have been configured in Saflivo yet. Add identification types before configuring organization requirements.</p>
        </section>
    @else
        <form wire:submit="save">
            @foreach ($settings as $typeId => $setting)
                <section class="card" wire:key="identification-{{ $typeId }}">
                    <div class="page-head">
                        <div>
                            <h2>{{ $setting['name'] }}</h2>
                            <div class="muted">{{ $setting['code'] }}@if ($setting['description'] !== '') · {{ $setting['description'] }}@endif</div>
                        </div>
                    </div>

                    <div class="grid">
                        <label class="field"><span><input type="checkbox" wire:model="settings.{{ $typeId }}.is_enabled"> Enabled for this organization</span></label>
                        <label class="field"><span><input type="checkbox" wire:model="settings.{{ $typeId }}.is_required"> Identification detail is mandatory</span></label>
                        <label class="field"><span><input type="checkbox" wire:model="settings.{{ $typeId }}.requires_document"> Require proof upload</span></label>
                        <label class="field"><span><input type="checkbox" wire:model="settings.{{ $typeId }}.has_expiry"> Document has expiry / expiry reminders apply</span></label>
                    </div>

                    <div class="field">
                        <label>Required upload(s)</label>
                        <div class="grid">
                            <label><input type="checkbox" value="document" wire:model="settings.{{ $typeId }}.document_requirements"> Single document</label>
                            <label><input type="checkbox" value="front" wire:model="settings.{{ $typeId }}.document_requirements"> Front side</label>
                            <label><input type="checkbox" value="back" wire:model="settings.{{ $typeId }}.document_requirements"> Back side</label>
                        </div>
                        <div class="muted">Choose a single document or front/back sides. A back side requires the front side.</div>
                        @error("settings.$typeId.document_requirements") <div class="error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label>Expiry reminder days before expiry</label>
                        <div class="grid">
                            @foreach ([0 => 'On expiry', 7 => '7 days', 15 => '15 days', 30 => '30 days', 60 => '60 days', 90 => '90 days'] as $days => $label)
                                <label><input type="checkbox" value="{{ $days }}" wire:model="settings.{{ $typeId }}.expiry_reminder_days"> {{ $label }}</label>
                            @endforeach
                        </div>
                        @error("settings.$typeId.expiry_reminder_days") <div class="error">{{ $message }}</div> @enderror
                    </div>
                </section>
            @endforeach

            <div class="actions">
                <button class="btn" type="submit" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">Save identification requirements</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            </div>
            @error('settings') <div class="error">{{ $message }}</div> @enderror
        </form>
    @endif
</div>
